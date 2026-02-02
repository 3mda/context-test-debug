<?php

namespace ContextTest\Symfony\Context\Collector;

class SymfonyLogCollector extends AbstractSymfonyCollector
{
    public function collect(array $context = []): array
    {
        $logs = $context['logs'] ?? [];
        if (empty($logs)) {
            return [];
        }
        return ['content' => implode("\n", $logs)];
    }
}
