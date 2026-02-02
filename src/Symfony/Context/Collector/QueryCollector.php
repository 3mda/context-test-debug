<?php

namespace ContextTest\Symfony\Context\Collector;

use Symfony\Component\DependencyInjection\ContainerInterface;

class QueryCollector extends AbstractSymfonyCollector
{
    public function collect(array $context = []): array
    {
        $client = $context['client'] ?? null;
        if ($client && method_exists($client, 'getContainer')) {
            $container = $client->getContainer();
        } else {
            $container = $context['container'] ?? null;
        }

        if (!$container instanceof ContainerInterface || !$container->has('profiler')) {
            return [];
        }

        try {
            $profiler = $container->get('profiler');
            $dbCollector = $profiler->has('db') ? $profiler->get('db') : ($profiler->has('doctrine') ? $profiler->get('doctrine') : null);
            if (!$dbCollector || !method_exists($dbCollector, 'getQueries')) {
                return [];
            }
            try {
                $queries = @$dbCollector->getQueries();
            } catch (\Throwable $e) {
                $queries = [];
            }
            if (!is_iterable($queries)) {
                $queries = [];
            }
            $data = [];
            foreach ($queries as $connectionQueries) {
                if (!is_array($connectionQueries)) {
                    continue;
                }
                foreach ($connectionQueries as $query) {
                    $data[] = [
                        'sql' => $query['sql'] ?? '',
                        'params' => $query['params'] ?? [],
                        'time_ms' => $query['executionMS'] ?? 0,
                    ];
                }
            }
            return ['count' => count($data), 'log' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'Could not collect queries: ' . $e->getMessage()];
        }
    }
}