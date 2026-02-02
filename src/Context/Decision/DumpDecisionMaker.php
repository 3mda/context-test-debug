<?php

namespace ContextTest\Context\Decision;

use ContextTest\Bridge\EnableContextDump;
use ReflectionMethod;

class DumpDecisionMaker
{
    public function __construct(
        private array $env = []
    ) {
    }

    public function decide(?ReflectionMethod $testMethod = null): bool
    {
        if (!empty($this->env['TEST_FORCE_LOGS'])) {
            return true;
        }

        if ($testMethod && class_exists(EnableContextDump::class)) {
            if (!empty($testMethod->getAttributes(EnableContextDump::class))) {
                return true;
            }
        }

        return false;
    }
}
