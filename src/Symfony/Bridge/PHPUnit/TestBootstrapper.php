<?php

namespace ContextTest\Symfony\Bridge\PHPUnit;

use ContextTest\Context\PhpErrorLogBuffer;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Bootstrap Symfony pour les tests (env, profiler, PHP errors, logs, DB).
 * Le projet hôte doit définir KERNEL_CLASS (son TestKernel) avant d'appeler bootstrap().
 * Optionnel : une classe avec méthodes statiques getProjectDir(), getLogDir(), configure(), etc.
 */
class TestBootstrapper
{
    private string $logSuffix;
    private ?string $pathsClass;

    /**
     * @param string|null $kernelClass Classe Kernel de test (ex. App\Testing\Bridge\Symfony\TestKernel). Si null, KERNEL_CLASS doit déjà être défini.
     * @param string|null $pathsClass  Classe avec getProjectDir(), getLogDir(), configure(), etc. (ex. App\Testing\Utils\TestPaths). Si null, tente App\Testing\Utils\TestPaths puis getcwd().
     */
    public static function bootstrap(?string $kernelClass = null, ?string $pathsClass = null): void
    {
        (new self($pathsClass))->run($kernelClass);
    }

    public function __construct(?string $pathsClass = null)
    {
        $this->logSuffix = sprintf('%s-%s', getmypid(), uniqid());
        $this->pathsClass = $pathsClass ?? (class_exists(\App\Testing\Utils\TestPaths::class) ? \App\Testing\Utils\TestPaths::class : null);
        if ($this->pathsClass && method_exists($this->pathsClass, 'configure')) {
            $this->pathsClass::configure(
                sprintf('phpunit-phpErrorLog-%s.log', $this->logSuffix),
                sprintf('phpunit-%s-%s.log', 'symfonyTestLog', $this->logSuffix),
                sprintf('phpunit.datacontext-%s.txt', $this->logSuffix),
                'symfonyTestLog'
            );
        }
    }

    private function run(?string $kernelClass): void
    {
        $this->loadEnv();

        if (($_SERVER['APP_DEBUG'] ?? false)) {
            umask(0000);
        }

        if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'test') {
            if ($kernelClass !== null) {
                $_SERVER['KERNEL_CLASS'] = $kernelClass;
                $_ENV['KERNEL_CLASS'] = $kernelClass;
                putenv('KERNEL_CLASS=' . $kernelClass);
            }
            $this->configureProfilerCollectFalse();
            $this->configurePhp();
            $this->cleanupArtifacts();
            $this->configureLogging();
            $this->resetDatabase();
        }
    }

    private function getProjectDir(): string
    {
        if ($this->pathsClass && method_exists($this->pathsClass, 'getProjectDir')) {
            return $this->pathsClass::getProjectDir();
        }
        return getcwd() ?: dirname(__DIR__, 5);
    }

    private function getLogDir(): string
    {
        if ($this->pathsClass && method_exists($this->pathsClass, 'getLogDir')) {
            return $this->pathsClass::getLogDir();
        }
        $dir = $this->getProjectDir() . '/var/log';
        if ($token = getenv('TEST_TOKEN')) {
            $dir .= '/test_' . $token;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
        return $dir;
    }

    private function getPhpErrorLogPath(): string
    {
        if ($this->pathsClass && method_exists($this->pathsClass, 'getPhpErrorLogPath')) {
            return $this->pathsClass::getPhpErrorLogPath();
        }
        return $this->getLogDir() . '/phpunit_errors.log';
    }

    private function getContextJunitPath(): string
    {
        if ($this->pathsClass && method_exists($this->pathsClass, 'getContextJunitPath')) {
            return $this->pathsClass::getContextJunitPath();
        }
        return $this->getLogDir() . '/phpunit.datacontext.junit';
    }

    private function configureProfilerCollectFalse(): void
    {
        $_SERVER['APP_PROFILER_COLLECT_IN_TEST'] = '0';
        $_ENV['APP_PROFILER_COLLECT_IN_TEST'] = '0';
        putenv('APP_PROFILER_COLLECT_IN_TEST=0');
    }

    private function loadEnv(): void
    {
        $projectDir = $this->getProjectDir();
        $envFile = $projectDir . '/.env';
        if (is_file($envFile) && method_exists(Dotenv::class, 'bootEnv')) {
            (new Dotenv())->bootEnv($envFile);
        }
    }

    private function configurePhp(): void
    {
        $_SERVER['APP_DEBUG'] = true;
        $_ENV['APP_DEBUG'] = true;
        putenv('APP_DEBUG=1');

        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        ini_set('log_errors', '1');
        ini_set('error_log', $this->getPhpErrorLogPath());

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            PhpErrorLogBuffer::push($severity, $message, $file, $line);
            return false;
        });

        ini_set('memory_limit', '-1');
    }

    private function cleanupArtifacts(): void
    {
        $logDir = $this->getLogDir();
        $phpErrorPath = $this->getPhpErrorLogPath();
        $contextPath = $this->getContextJunitPath();

        $files = [$logDir . '/phpunit_errors.log', $logDir . '/test.log', $logDir . '/phpunit.results.junit', $logDir . '/dev.log'];
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $patterns = ['/phpunit-symfony-test-log-*.log', '/phpunit-phpErrorLog-*.log', '/phpunit-symfonyTestLog-*.log', '/phpunit.datacontext-*.yaml', '/phpunit.datacontext-*.txt'];
        $currentFiles = [$phpErrorPath, $contextPath];
        if ($this->pathsClass && method_exists($this->pathsClass, 'getSymfonyLogPath')) {
            $currentFiles[] = $this->pathsClass::getSymfonyLogPath();
        }
        foreach ($patterns as $pattern) {
            foreach (glob($logDir . $pattern) ?: [] as $file) {
                if (!in_array($file, $currentFiles, true)) {
                    @unlink($file);
                }
            }
        }

        error_log(sprintf(">>> PHP Error Log initialized for session %s <<<", $this->logSuffix));
    }

    private function configureLogging(): void
    {
        $filename = 'test.log';
        if ($this->pathsClass && method_exists($this->pathsClass, 'getSymfonyLogFilename')) {
            $filename = $this->pathsClass::getSymfonyLogFilename();
        }
        $_SERVER['TEST_LOG_FILENAME'] = $filename;
        $_ENV['TEST_LOG_FILENAME'] = $filename;
        putenv('TEST_LOG_FILENAME=' . $filename);
    }

    private function resetDatabase(): void
    {
        $projectDir = $this->getProjectDir();
        $kernelClass = $_SERVER['KERNEL_CLASS'] ?? null;
        if (!$kernelClass || !class_exists($kernelClass)) {
            return;
        }

        passthru(sprintf('%s "%s/bin/console" cache:clear --env=test', PHP_BINARY, $projectDir));

        try {
            $kernel = new $kernelClass($_SERVER['APP_ENV'], (bool) ($_SERVER['APP_DEBUG'] ?? false));
            $kernel->boot();

            $application = new Application($kernel);
            $application->setAutoExit(false);
            $output = new ConsoleOutput();

            $application->run(new ArrayInput(['command' => 'doctrine:schema:drop', '--force' => true]), $output);
            $application->run(new ArrayInput(['command' => 'doctrine:schema:create']), $output);

            $kernel->shutdown();
        } catch (\Throwable $e) {
            $this->logError($e);
            throw $e;
        }
    }

    private function logError(\Throwable $e): void
    {
        $logEntry = sprintf("[%s] Bootstrap Error: %s\n%s\n", date('Y-m-d H:i:s'), $e->getMessage(), $e->getTraceAsString());
        file_put_contents($this->getPhpErrorLogPath(), $logEntry, FILE_APPEND);
    }
}
