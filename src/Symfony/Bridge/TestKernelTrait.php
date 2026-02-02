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
            $dir = $this->getProjectDir() . '/var/log/test_' . $token;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            return $dir;
        }
        return parent::getLogDir();
    }

    public function getPhpErrorLogPath(): string
    {
        return $this->getLogDir() . '/' . (getenv('TEST_PHP_ERROR_LOG_FILENAME') ?: 'phpunit_errors.log');
    }

    public function getContextJunitPath(): string
    {
        return $this->getLogDir() . '/' . (getenv('TEST_CONTEXT_JUNIT_FILENAME') ?: 'phpunit.datacontext.junit');
    }

    public function getResultsJunitPath(): string
    {
        return $this->getLogDir() . '/phpunit.results.junit';
    }

    public function getSymfonyLogFilename(): string
    {
        return getenv('TEST_LOG_FILENAME') ?: 'test.log';
    }

    public function getSymfonyLogPath(): string
    {
        return $this->getLogDir() . '/' . $this->getSymfonyLogFilename();
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
