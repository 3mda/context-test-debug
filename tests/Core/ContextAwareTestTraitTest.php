<?php

namespace ContextTest\Tests\Core;

use App\Testing\Bridge\PHPUnit\ContextAwareTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests the core of context-test-debug: trait, steps, dump on failure.
 * Uses App\Testing while the package code lives in the host project.
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
}
