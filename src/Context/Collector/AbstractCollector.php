<?php

namespace ContextTest\Context\Collector;

/**
 * Base pour les collecteurs de contexte (core PHP 8, sans dépendance Symfony).
 */
abstract class AbstractCollector
{
    public function getName(): string
    {
        return str_replace('Collector', '', (new \ReflectionClass($this))->getShortName());
    }

    abstract public function collect(array $context = []): array;

    /**
     * @param array<string, callable> $strategies
     */
    protected function executeStrategies(array $strategies): array
    {
        $report = [];
        $isMultiple = count($strategies) > 1;

        foreach ($strategies as $name => $strategy) {
            if (!$isMultiple) {
                return $strategy() ?? [];
            }

            $start = microtime(true);
            $startMem = memory_get_usage();

            try {
                $result = $strategy();
                if ($result === null) {
                    $status = 'skipped';
                    $data = null;
                } elseif (isset($result['error'])) {
                    $status = 'failure';
                    $data = $result;
                } elseif (empty($result)) {
                    $status = 'empty';
                    $data = null;
                } else {
                    $status = 'success';
                    $data = $result;
                }
            } catch (\Throwable $e) {
                $data = ['exception' => $e->getMessage()];
                $status = 'error';
            }

            $end = microtime(true);
            $endMem = memory_get_usage();

            $report[$name] = [
                'status' => $status,
                'time_ms' => round(($end - $start) * 1000, 3),
                'memory_kb' => round(($endMem - $startMem) / 1024, 3),
                'data' => $data,
            ];
        }

        return $report;
    }
}
