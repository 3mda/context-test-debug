<?php

namespace ContextTest\Symfony\Bridge;

use ContextTest\Symfony\Bridge\Log\TestInMemoryLogHandler;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Trait à utiliser dans le Kernel de test du projet hôte.
 * Isole cache/logs par processus (Paratest) et enregistre le handler Monolog en mémoire.
 *
 * Exemple (dans le projet hôte) :
 *   class TestKernel extends App\Kernel { use TestKernelTrait; }
 */
trait TestKernelTrait
{
    public function getCacheDir(): string
    {
        if ($token = getenv('TEST_TOKEN')) {
            return $this->getProjectDir() . '/var/cache/test_' . $token;
        }
        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ($token = getenv('TEST_TOKEN')) {
            return $this->getProjectDir() . '/var/log/test_' . $token;
        }
        return parent::getLogDir();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MonologTestLogPass());
        parent::build($container);
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(function (ContainerBuilder $container) {
            $container->register('app.testing.log_handler', TestInMemoryLogHandler::class)
                ->setPublic(true);

            $container->loadFromExtension('monolog', [
                'handlers' => [
                    'testing_memory' => [
                        'type' => 'service',
                        'id' => 'app.testing.log_handler',
                        'level' => 'debug',
                        'priority' => 999,
                        'channels' => ['!event'],
                    ],
                ],
            ]);
        });
    }
}
