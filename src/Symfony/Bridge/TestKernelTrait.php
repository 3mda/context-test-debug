<?php

namespace ContextTest\Symfony\Bridge;

use ContextTest\Bridge\PHPUnit\DefaultTestPaths;
use ContextTest\Symfony\Bridge\Log\TestInMemoryLogHandler;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Trait à utiliser dans le Kernel de test du projet hôte.
 * Isole cache/logs par processus (Paratest) et enregistre le handler Monolog en mémoire.
 * Délègue à DefaultTestPaths pour la résolution des chemins (source unique).
 *
 * Exemple (dans le projet hôte) :
 *   class TestKernel extends App\Kernel { use TestKernelTrait; }
 */
trait TestKernelTrait
{
    public function getCacheDir(): string
    {
        if (getenv('TEST_TOKEN')) {
            return DefaultTestPaths::getCacheDir($this->getProjectDir());
        }
        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        return DefaultTestPaths::getLogDir($this->getProjectDir());
    }

    public function getPhpErrorLogPath(): string
    {
        return DefaultTestPaths::getPhpErrorLogPath($this->getProjectDir());
    }

    public function getContextJunitPath(): string
    {
        return DefaultTestPaths::getContextJunitPath($this->getProjectDir());
    }

    public function getResultsJunitPath(): string
    {
        return DefaultTestPaths::getResultsJunitPath($this->getProjectDir());
    }

    public function getSymfonyLogFilename(): string
    {
        return DefaultTestPaths::getSymfonyLogFilename();
    }

    public function getSymfonyLogPath(): string
    {
        return DefaultTestPaths::getSymfonyLogPath($this->getProjectDir());
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
