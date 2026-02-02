<?php

/**
 * Bootstrap for context-test-debug package tests.
 * When the package lives at vendor/3mda/context-test-debug/, __DIR__ is .../context-test-debug/tests.
 */
$packageRoot = dirname(__DIR__);

// 1. Charger l'autoload du projet hôte (quand le package est dans vendor/)
$vendorAutoload = '/app/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} elseif (is_file(dirname($packageRoot) . '/autoload.php')) {
    require dirname($packageRoot) . '/autoload.php';
} else {
    require $packageRoot . '/vendor/autoload.php';
}

// 2. Garantir que le namespace ContextTest\ charge bien les classes du package (au cas où
//    l'autoload du host n'a pas été régénéré après ajout des fichiers dans src/)
spl_autoload_register(static function (string $class) use ($packageRoot): void {
    if (str_starts_with($class, 'ContextTest\\')) {
        $file = $packageRoot . '/src/' . str_replace('\\', '/', substr($class, 12)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, prepend: false);

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

// Dossier de sortie des rapports de contexte quand on lance les tests du package (chemin absolu)
$outputDir = realpath($packageRoot) ?: $packageRoot;
$outputDir = rtrim($outputDir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'log';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0777, true);
}
if (!defined('CONTEXT_TEST_OUTPUT_DIR')) {
    define('CONTEXT_TEST_OUTPUT_DIR', $outputDir);
}
putenv('CONTEXT_TEST_OUTPUT_DIR=' . $outputDir);
$_ENV['CONTEXT_TEST_OUTPUT_DIR'] = $outputDir;
$_SERVER['CONTEXT_TEST_OUTPUT_DIR'] = $outputDir;

// Capture des erreurs PHP en mémoire pour les inclure dans le dump de contexte
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    \ContextTest\Context\PhpErrorLogBuffer::push($severity, $message, $file, $line);
    return false;
});

// Full bootstrap (cache, DB, etc.) only when the host project runs its suite.
if (getenv('CONTEXT_TEST_FULL_BOOTSTRAP') === '1' && class_exists(\App\Testing\Bridge\PHPUnit\TestBootstrapper::class)) {
    \App\Testing\Bridge\PHPUnit\TestBootstrapper::bootstrap();
}
