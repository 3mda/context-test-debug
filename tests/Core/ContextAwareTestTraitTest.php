<?php

namespace ContextTest\Tests\Core;

use ContextTest\Bridge\PHPUnit\ContextAwareTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests the core of context-test-debug: trait, steps, dump on failure.
 */
class ContextAwareTestTraitTest extends TestCase
{
    use ContextAwareTestTrait;

    public function test_steps_are_recorded(): void
    {
        $this->logStep('Step one');
        $this->logStep('Step two');
        $tracker = $this->getTraceTracker();
        $steps = $tracker->getSteps();
        $this->assertCount(2, $steps);
        $this->assertSame('Step one', $steps[0]['action'] ?? null);
        $this->assertSame('Step two', $steps[1]['action'] ?? null);
    }

    public function test_passes_without_dump(): void
    {
        $this->logStep('Passing test');
        $this->assertTrue(true);
    }

    /**
     * Déclenche une notice PHP pour vérifier qu'elle apparaît dans le rapport (PhpErrorLogBuffer).
     * Lancer avec TEST_FORCE_LOGS=1 pour générer le dump même si le test passe.
     */
    public function test_php_notice_appears_in_report(): void
    {
        $this->logStep('Before notice');
        // Undefined variable → E_NOTICE capturée par set_error_handler → PhpErrorLogBuffer
        $dummy = $undefinedVariable;
        $this->logStep('After notice');
        $this->assertTrue(true);
    }
}
