<?php

namespace ContextTest\Context\Decision;

use ContextTest\Bridge\EnableContextDump;
use ReflectionMethod;

class DumpDecisionMaker
{
    private const DEBUG_KEY = 'DEBUG';

    /** Values that enable context dump (case-insensitive). */
    private const DEBUG_TRUTHY = ['1', 'true', 'yes', 'on'];

    /** Values that explicitly disable context dump (case-insensitive). */
    private const DEBUG_FALSY = ['0', 'false', 'no', 'off', ''];

    public function __construct(
        private array $env = []
    ) {
    }

    /**
     * Détermine si un dump de contexte doit être généré.
     *
     * Ordre d'évaluation : (1) DEBUG falsy → (2) échec → (3) DEBUG truthy → (4) #[EnableContextDump].
     *
     * - (1) DEBUG falsy (0, false, no, off) → pas de dump (module désactivé, même sur échec).
     * - (2) Test failed/error → dump.
     * - (3) DEBUG truthy (1, true, yes, on) → dump pour tous les tests.
     * - (4) Attribut sur la méthode → dump pour ce test (seulement si DEBUG non défini ou non falsy).
     */
    public function decide(?ReflectionMethod $testMethod = null, bool $hasFailed = false): bool
    {
        if ($this->isDebugEnvFalsy()) {
            return false;
        }

        if ($hasFailed) {
            return true;
        }

        if ($this->isDebugEnvTruthy()) {
            return true;
        }

        if ($testMethod && class_exists(EnableContextDump::class)) {
            if (!empty($testMethod->getAttributes(EnableContextDump::class))) {
                return true;
            }
        }

        return false;
    }

    /**
     * DEBUG is read case-insensitively. Truthy: 1, true, yes, on. Falsy: 0, false, no, off, empty.
     */
    private function isDebugEnvTruthy(): bool
    {
        $value = $this->getDebugEnvValue();
        if ($value === null) {
            return false;
        }
        $normalized = is_scalar($value) ? strtolower((string) $value) : '';
        return \in_array($normalized, self::DEBUG_TRUTHY, true);
    }

    /** DEBUG explicitly set to a falsy value → module disabled (no dump, including on failure). */
    private function isDebugEnvFalsy(): bool
    {
        $value = $this->getDebugEnvValue();
        if ($value === null) {
            return false;
        }
        $normalized = is_scalar($value) ? strtolower((string) $value) : '';
        return \in_array($normalized, self::DEBUG_FALSY, true);
    }

    private function getDebugEnvValue(): mixed
    {
        foreach ($this->env as $key => $val) {
            if (strcasecmp((string) $key, self::DEBUG_KEY) === 0) {
                return $val;
            }
        }
        return null;
    }
}
