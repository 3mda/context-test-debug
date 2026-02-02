<?php

namespace ContextTest\Symfony\Context\Collector;

use ContextTest\Context\Collector\AbstractCollector;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.testing.collector')]
abstract class AbstractSymfonyCollector extends AbstractCollector
{
}
