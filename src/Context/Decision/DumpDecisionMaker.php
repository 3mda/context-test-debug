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

    /**
     * Détermine si un dump de contexte doit être généré.
     *
     * Conditions (dans l'ordre) :
     * 1. Le test a échoué (failure ou error)
     * 2. DEBUG est défini
     * 3. La méthode de test possède l'attribut #[EnableContextDump]
     */
    public function decide(?ReflectionMethod $testMethod = null, bool $hasFailed = false): bool
    {
        if ($hasFailed) {
            return true;
        }

        if (!empty($this->env['DEBUG'])) {
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
