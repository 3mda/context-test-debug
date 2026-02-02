<?php

namespace ContextTest\Symfony\Bridge;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Force les handlers Monolog StreamHandler à écrire vers le fichier de log de test.
 */
final class MonologTestLogPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $logsDir = $container->getParameterBag()->resolveValue('%kernel.logs_dir%');
        $filename = getenv('TEST_LOG_FILENAME') ?: 'test.log';
        $newPath = $logsDir . '/' . $filename;

        foreach ($container->getDefinitions() as $id => $definition) {
            if (strpos($id, 'monolog.handler.') !== 0) {
                continue;
            }
            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if ($class === 'Monolog\Handler\StreamHandler' || is_subclass_of($class, 'Monolog\Handler\StreamHandler')) {
                $definition->setArgument(0, $newPath);
            }
        }
    }
}
