<?php

namespace ContextTest\Symfony\Context;

use ContextTest\Context\ContextSnapshotter as CoreContextSnapshotter;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Snapshotter Symfony : étend le core et injecte les collecteurs tagués (app.testing.collector).
 */
class ContextSnapshotter extends CoreContextSnapshotter
{
    public function __construct(
        #[TaggedIterator('app.testing.collector')] iterable $collectors
    ) {
        parent::__construct($collectors);
    }
}
