<?php

namespace ContextTest\Context\State;

class TraceTracker
{
    private array $steps = [];

    public function addStep(string $action, ?string $requestSummary = null, ?int $statusCode = null, array $extraData = []): void
    {
        $step = [
            'step' => count($this->steps) + 1,
            'action' => $action,
            'timestamp' => (new \DateTime())->format('H:i:s.u'),
            'memory' => sprintf('%.2f MB', memory_get_usage(true) / 1024 / 1024),
            'request' => $requestSummary,
            'status' => $statusCode,
        ];

        if (!empty($extraData)) {
            $step = array_merge($step, $extraData);
        }

        $this->steps[] = $step;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function clear(): void
    {
        $this->steps = [];
    }
}
