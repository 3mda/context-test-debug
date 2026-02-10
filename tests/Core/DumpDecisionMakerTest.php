<?php

namespace ContextTest\Tests\Core;

use ContextTest\Bridge\EnableContextDump;
use ContextTest\Context\Decision\DumpDecisionMaker;
use PHPUnit\Framework\TestCase;

/**
 * Tests DEBUG env: case-insensitive key, truthy/falsy values, and DEBUG falsy wins over attribute.
 */
class DumpDecisionMakerTest extends TestCase
{
    /** Helper class for testing attribute vs DEBUG. */
    private const CLASS_WITH_ATTRIBUTE = DumpDecisionMakerTestTarget::class;
    /** @dataProvider debugTruthyProvider */
    public function test_debug_env_truthy_enables_dump(array $env): void
    {
        $maker = new DumpDecisionMaker($env);
        self::assertTrue($maker->decide(null, false), 'DEBUG truthy should enable dump');
    }

    public static function debugTruthyProvider(): array
    {
        return [
            'DEBUG=1' => [['DEBUG' => '1']],
            'DEBUG=true' => [['DEBUG' => 'true']],
            'DEBUG=yes' => [['DEBUG' => 'yes']],
            'DEBUG=on' => [['DEBUG' => 'on']],
            'debug=1' => [['debug' => '1']],
            'Debug=true' => [['Debug' => 'true']],
        ];
    }

    /** @dataProvider debugFalsyProvider */
    public function test_debug_env_falsy_does_not_enable_dump(array $env): void
    {
        $maker = new DumpDecisionMaker($env);
        self::assertFalse($maker->decide(null, false), 'DEBUG falsy should not enable dump');
    }

    public static function debugFalsyProvider(): array
    {
        return [
            'DEBUG=0' => [['DEBUG' => '0']],
            'DEBUG=false' => [['DEBUG' => 'false']],
            'DEBUG=no' => [['DEBUG' => 'no']],
            'DEBUG=off' => [['DEBUG' => 'off']],
            'DEBUG=empty' => [['DEBUG' => '']],
            'debug=0' => [['debug' => '0']],
            'Debug=false' => [['Debug' => 'false']],
        ];
    }

    public function test_no_debug_key_does_not_enable_dump(): void
    {
        $maker = new DumpDecisionMaker(['OTHER' => '1']);
        self::assertFalse($maker->decide(null, false));
    }

    /** DEBUG falsy disables the module: no dump even on test failure (e.g. to debug the package itself). */
    public function test_debug_falsy_disables_dump_even_on_failure(): void
    {
        $maker = new DumpDecisionMaker(['DEBUG' => '0']);
        self::assertFalse($maker->decide(null, true), 'DEBUG=0 should disable dump even when test fails');
    }

    /**
     * When DEBUG is falsy, it wins over #[EnableContextDump]: no dump for passing test.
     */
    public function test_debug_falsy_wins_over_attribute(): void
    {
        $method = new \ReflectionMethod(self::CLASS_WITH_ATTRIBUTE, 'methodWithAttribute');
        self::assertNotEmpty($method->getAttributes(EnableContextDump::class), 'Test target must have attribute');

        $maker = new DumpDecisionMaker(['DEBUG' => '0']);
        self::assertFalse($maker->decide($method, false), 'DEBUG=0 should disable dump even when method has #[EnableContextDump]');
    }

    /**
     * Full decision matrix: DEBUG (not set / truthy / falsy) × #[EnableContextDump] (absent / present) × test (pass / fail).
     * See doc/DUMP_DECISION_MATRIX.md for the reference table.
     *
     * @dataProvider decideMatrixProvider
     */
    public function test_decide_matrix(array $env, bool $withAttribute, bool $hasFailed, bool $expectDump): void
    {
        $method = new \ReflectionMethod(
            DumpDecisionMakerTestTarget::class,
            $withAttribute ? 'methodWithAttribute' : 'methodWithoutAttribute'
        );
        $maker = new DumpDecisionMaker($env);
        self::assertSame($expectDump, $maker->decide($method, $hasFailed));
    }

    public static function decideMatrixProvider(): array
    {
        $none = [];
        $truthy = ['DEBUG' => '1'];
        $falsy = ['DEBUG' => '0'];

        return [
            'DEBUG not set, no attr, pass' => [$none, false, false, false],
            'DEBUG not set, no attr, fail' => [$none, false, true, true],
            'DEBUG not set, attr, pass' => [$none, true, false, true],
            'DEBUG not set, attr, fail' => [$none, true, true, true],
            'DEBUG truthy, no attr, pass' => [$truthy, false, false, true],
            'DEBUG truthy, no attr, fail' => [$truthy, false, true, true],
            'DEBUG truthy, attr, pass' => [$truthy, true, false, true],
            'DEBUG truthy, attr, fail' => [$truthy, true, true, true],
            'DEBUG falsy, no attr, pass' => [$falsy, false, false, false],
            'DEBUG falsy, no attr, fail' => [$falsy, false, true, false],
            'DEBUG falsy, attr, pass' => [$falsy, true, false, false],
            'DEBUG falsy, attr, fail' => [$falsy, true, true, false],
        ];
    }
}

/**
 * @internal
 */
class DumpDecisionMakerTestTarget
{
    #[EnableContextDump]
    public function methodWithAttribute(): void
    {
    }

    /** Method without #[EnableContextDump] for matrix tests. */
    public function methodWithoutAttribute(): void
    {
    }
}
