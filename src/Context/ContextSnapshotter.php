<?php

namespace ContextTest\Context;

use ContextTest\Context\Collector\AbstractCollector;

/**
 * Agrège les collecteurs de contexte (core PHP 8, sans DI Symfony).
 *
 * @param iterable<AbstractCollector> $collectors
 */
class ContextSnapshotter
{
    private iterable $collectors;

    public function __construct(iterable $collectors)
    {
        $this->collectors = $collectors;
    }

    public function collect(array $context = []): array
    {
        $snapshot = [];

        foreach ($this->collectors as $collector) {
            $snapshot[$collector->getName()] = $collector->collect($context);
        }

        return $snapshot;
    }
}
