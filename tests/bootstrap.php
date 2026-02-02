<?php

/**
 * Bootstrap for context-test-debug package tests.
 * When the package lives at vendor/3mda/context-test-debug/, __DIR__ is .../context-test-debug/tests.
 * Two levels up = host project's vendor/ dir, so vendor/autoload.php = dirname(__DIR__, 2) . '/autoload.php'.
 */
$vendorAutoload = '/app/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require dirname(__DIR__) . '/vendor/autoload.php';
}

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
// Full bootstrap (cache, DB, etc.) only when the host project runs its suite.
// When running only the package tests (from vendor/3mda/context-test-debug), skip it.
if (getenv('CONTEXT_TEST_FULL_BOOTSTRAP') === '1' && class_exists(\App\Testing\Bridge\PHPUnit\TestBootstrapper::class)) {
    \App\Testing\Bridge\PHPUnit\TestBootstrapper::bootstrap();
}
