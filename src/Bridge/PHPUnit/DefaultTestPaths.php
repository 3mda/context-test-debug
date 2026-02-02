<?php

namespace ContextTest\Bridge\PHPUnit;

/**
 * Source unique des chemins de test (PHP pur et Symfony).
 * Le TestKernelTrait Symfony délègue à cette classe pour éviter la duplication.
 */
final class DefaultTestPaths
{
    public static function getProjectDir(): string
    {
        return getcwd() ?: dirname(__DIR__, 3);
    }

    public static function getCacheDir(?string $projectDir = null): string
    {
        $base = $projectDir ?? self::getProjectDir();
        $token = getenv('TEST_TOKEN');
        return $token ? $base . '/var/cache/test_' . $token : $base . '/var/cache';
    }

    public static function getLogDir(?string $projectDir = null): string
    {
        $base = $projectDir ?? self::getProjectDir();
        $dir = $base . '/var/log';
        if ($token = getenv('TEST_TOKEN')) {
            $dir .= '/test_' . $token;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
        return $dir;
    }

    public static function getPhpErrorLogPath(?string $projectDir = null): string
    {
        return self::getLogDir($projectDir) . '/' . (getenv('TEST_PHP_ERROR_LOG_FILENAME') ?: 'phpunit_errors.log');
    }

    public static function getContextJunitPath(?string $projectDir = null): string
    {
        return self::getLogDir($projectDir) . '/' . (getenv('TEST_CONTEXT_JUNIT_FILENAME') ?: 'phpunit.datacontext.junit');
    }

    public static function getResultsJunitPath(?string $projectDir = null): string
    {
        return self::getLogDir($projectDir) . '/phpunit.results.junit';
    }

    public static function getSymfonyLogFilename(): string
    {
        return getenv('TEST_LOG_FILENAME') ?: 'test.log';
    }

    public static function getSymfonyLogPath(?string $projectDir = null): string
    {
        return self::getLogDir($projectDir) . '/' . self::getSymfonyLogFilename();
    }
}
