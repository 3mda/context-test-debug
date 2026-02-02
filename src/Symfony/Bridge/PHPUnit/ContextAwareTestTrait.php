<?php

namespace ContextTest\Symfony\Bridge\PHPUnit;

use ContextTest\Bridge\PHPUnit\ContextAwareTestTrait as CoreContextAwareTestTrait;
use ContextTest\Context\Collector\PhpErrorLogCollector;
use ContextTest\Context\ContextSnapshotter;
use ContextTest\Symfony\Bridge\Client\TraceableClientFactory;
use ContextTest\Symfony\Context\Collector\BrowserCollector;
use ContextTest\Symfony\Context\Collector\MailerCollector;
use ContextTest\Symfony\Context\Collector\QueryCollector;
use ContextTest\Symfony\Context\Collector\SessionCollector;
use ContextTest\Symfony\Context\Collector\SymfonyLogCollector;

/**
 * Trait Symfony : étend le core et ajoute createTestClient, logs Symfony, collecteurs Symfony.
 * Pour ajouter un collecteur spécifique au projet (ex. DatabaseCollector), surcharger getDefaultCollectors().
 */
trait ContextAwareTestTrait
{
    use CoreContextAwareTestTrait;

    private ?TraceableClientFactory $traceableClientFactory = null;

    protected function getTraceableClientFactory(): TraceableClientFactory
    {
        if (!$this->traceableClientFactory) {
            $this->traceableClientFactory = new TraceableClientFactory();
        }
        return $this->traceableClientFactory;
    }

    /**
     * Liste des collecteurs par défaut (sans DatabaseCollector).
     * Surcharger dans le projet pour ajouter DatabaseCollector ou d'autres.
     *
     * @return list<object> Collecteurs (instances de AbstractCollector / AbstractSymfonyCollector)
     */
    protected function getDefaultCollectors(): array
    {
        return [
            new BrowserCollector(),
            new SymfonyLogCollector(),
            new PhpErrorLogCollector(),
            new MailerCollector(),
            new SessionCollector(),
            new QueryCollector(),
        ];
    }

    protected function getContextSnapshotter(): ContextSnapshotter
    {
        if ($this->contextSnapshotter === null) {
            $this->contextSnapshotter = new ContextSnapshotter($this->getDefaultCollectors());
        }
        return $this->contextSnapshotter;
    }

    protected function createTestClient(array $options = [], array $server = []): object
    {
        return $this->getTraceableClientFactory()->create(
            fn() => static::createClient($options, $server),
            function (?object $request, ?object $response, object $client) {
                $description = ($request && method_exists($request, 'getMethod'))
                    ? sprintf('%s %s', $request->getMethod(), method_exists($request, 'getRequestUri') ? $request->getRequestUri() : (method_exists($request, 'getUri') ? $request->getUri() : ''))
                    : 'API request';
                $this->addContextStep(false, $description, $request, $response, $client);
            }
        );
    }

    protected function getLogsForContext(?object $client = null): array
    {
        return $this->getSymfonyLogs($client);
    }

    private function getSymfonyLogs(?object $client = null): array
    {
        try {
            $container = null;
            $records = [];

            if ($client && method_exists($client, 'getProfile')) {
                if (($profile = $client->getProfile()) && $profile->hasCollector('logger')) {
                    $records = $profile->getCollector('logger')->getLogs();
                }
            }

            if (method_exists($this, 'getContainer')) {
                try {
                    $container = static::getContainer();
                } catch (\Throwable $e) {}
            }

            if (empty($records) && $container) {
                if ($container->has('app.testing.log_handler')) {
                    $handler = $container->get('app.testing.log_handler');
                    if (method_exists($handler, 'getLogs')) {
                        $records = $handler->getLogs();
                    }
                }
                if (empty($records) && $container->has('profiler')) {
                    $profiler = $container->get('profiler');
                    if ($profiler->hasCollector('logger')) {
                        $records = $profiler->getCollector('logger')->getLogs();
                    }
                }
                if (empty($records) && $container->has('logger') && method_exists($container->get('logger'), 'getLogs')) {
                    $records = $container->get('logger')->getLogs();
                }
            }

            if (!empty($records)) {
                return array_map(function ($record) {
                    if (is_string($record)) {
                        return $record;
                    }
                    if (is_object($record)) {
                        $ts = $record->datetime;
                        $level = method_exists($record->level, 'getName') ? $record->level->getName() : $record->level;
                        $channel = $record->channel ?? '';
                        $message = $record->message;
                        $context = $record->context ?? [];
                    } else {
                        $ts = $record['datetime'] ?? $record['timestamp'] ?? new \DateTime();
                        $level = $record['level_name'] ?? $record['priorityName'] ?? 'INFO';
                        $channel = $record['channel'] ?? '';
                        $message = $record['message'] ?? '';
                        $context = $record['context'] ?? [];
                    }

                    $time = ($ts instanceof \DateTimeInterface) ? $ts->format('H:i:s.u') : date('H:i:s.u', (int)$ts);
                    $rawMessage = $message;
                    $messageInterpolated = $this->interpolateSymfonyLog($message, is_array($context) ? $context : (array) $context);
                    $line = $channel !== '' ? sprintf('[%s] [%s] %s: %s', $time, $level, $channel, $messageInterpolated) : sprintf('[%s] [%s] %s', $time, $level, $messageInterpolated);
                    $contextArray = is_array($context) ? $context : (array) $context;
                    return $this->formatLogEntryWithDetails($line, $time, $rawMessage, $contextArray);
                }, $records);
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    private function formatLogEntryWithDetails(string $line, string $time, string $rawMessage, array $context): string
    {
        $block = $line . "\n  " . $time . "\n  " . $rawMessage;
        if ($context !== []) {
            $block .= "\n" . $this->formatContextReadable($context);
        }
        return $block;
    }

    private function formatContextReadable(array $context, int $maxValueLen = 500): string
    {
        $lines = [];
        foreach ($context as $key => $value) {
            $str = $this->contextValueToString($value);
            if (strlen($str) > $maxValueLen) {
                $str = substr($str, 0, $maxValueLen) . '...';
            }
            $lines[] = '  ' . $key . ': ' . $str;
        }
        return implode("\n", $lines);
    }

    private function contextValueToString(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return $this->safeJsonEncode($value);
        }
        return is_object($value) ? ('[object ' . get_class($value) . ']') : (string) $value;
    }

    private function interpolateSymfonyLog(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replace['{' . $key . '}'] = $val->format('Y-m-d H:i:s');
            } elseif (is_object($val) && str_contains(get_class($val), 'VarDumper')) {
                $replace['{' . $key . '}'] = '[object]';
            } elseif (is_object($val)) {
                $replace['{' . $key . '}'] = '[object ' . get_class($val) . ']';
            } elseif (is_array($val)) {
                $replace['{' . $key . '}'] = $this->safeJsonEncode($val);
            } else {
                $replace['{' . $key . '}'] = '[' . gettype($val) . ']';
            }
        }
        return strtr($message, $replace);
    }

    private function safeJsonEncode(array $val): string
    {
        $sanitized = array_map(function ($item) {
            if (is_object($item)) {
                return sprintf('Object(%s)', get_class($item));
            }
            return $item;
        }, $val);

        $json = json_encode($sanitized);
        if ($json === false) {
            $json = json_encode(array_map(fn($v) => is_string($v) && !mb_check_encoding($v, 'UTF-8') ? base64_encode($v) : $v, $sanitized));
        }

        if ($json && strlen($json) > 5000) {
            $json = substr($json, 0, 5000) . '... (truncated)';
        }
        return $json ?: '[]';
    }
}
