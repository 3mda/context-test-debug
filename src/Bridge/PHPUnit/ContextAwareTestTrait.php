<?php

namespace ContextTest\Bridge\PHPUnit;

use ContextTest\Context\Collector\PhpErrorLogCollector;
use ContextTest\Context\ContextSnapshotter;
use ContextTest\Context\Decision\DumpDecisionMaker;
use ContextTest\Context\Report\ReportGenerator;
use ContextTest\Context\State\TraceTracker;
use PHPUnit\Runner\BaseTestRunner;

/**
 * Trait core PHP 8 pour la capture de contexte en test (sans dépendance Symfony).
 * Pour les WebTestCase Symfony, utiliser le trait qui étend celui-ci et ajoute
 * createTestClient, getSymfonyLogs et les collecteurs Symfony.
 */
trait ContextAwareTestTrait
{
    private ?TraceTracker $traceTracker = null;
    private ?DumpDecisionMaker $dumpDecisionMaker = null;
    private ?ReportGenerator $reportGenerator = null;

    /** Un seul dump (et un seul "D" CLI) par test, quel que soit le canal (DEBUG=1, annotation, échec). */
    private bool $contextDumpDone = false;

    /** @var ContextSnapshotter|null visible aux traits qui étendent (ex. Symfony) */
    protected ?ContextSnapshotter $contextSnapshotter = null;

    protected function getTraceTracker(): TraceTracker
    {
        if (!$this->traceTracker) {
            $this->traceTracker = new TraceTracker();
        }
        return $this->traceTracker;
    }

    protected function getDumpDecisionMaker(): DumpDecisionMaker
    {
        if (!$this->dumpDecisionMaker) {
            $this->dumpDecisionMaker = new DumpDecisionMaker(getenv() ?: []);
        }
        return $this->dumpDecisionMaker;
    }

    protected function getReportGenerator(): ReportGenerator
    {
        if (!$this->reportGenerator) {
            $this->reportGenerator = new ReportGenerator();
        }
        return $this->reportGenerator;
    }

    protected function getContextSnapshotter(): ContextSnapshotter
    {
        if (!$this->contextSnapshotter) {
            $this->contextSnapshotter = new ContextSnapshotter([
                new PhpErrorLogCollector(),
            ]);
        }
        return $this->contextSnapshotter;
    }

    protected function tearDown(): void
    {
        $this->runContextAwareTearDown();
        parent::tearDown();
    }

    /**
     * Logique de dump de contexte (enregistrement des échecs, génération du rapport).
     * À appeler depuis tearDown() si la classe surcharge tearDown.
     */
    protected function runContextAwareTearDown(): void
    {
        if ($this->hasFailed()) {
            $testName = method_exists($this, 'name') ? $this->name() : $this->getName(false);
            $GLOBALS['__PHPUNIT_FAILED_TESTS'][] = static::class . '::' . $testName;
        }

        if (!isset($GLOBALS['__PHPUNIT_SHUTDOWN_REGISTERED'])) {
            register_shutdown_function(function () {
                if (!empty($GLOBALS['__PHPUNIT_FAILED_TESTS'])) {
                    echo "\n\n\033[33m! [DEBUG HINT] Des tests ont échoué. Pour relancer uniquement ces tests avec le dump de contexte actif :\033[0m\n";
                    $filter = implode('|', array_map(fn($t) => str_replace('\\', '\\\\', $t), $GLOBALS['__PHPUNIT_FAILED_TESTS']));
                    echo sprintf("\n\033[32mDEBUG=1 vendor/bin/phpunit --filter \"%s\"\033[0m\n\n", $filter);
                }
            });
            $GLOBALS['__PHPUNIT_SHUTDOWN_REGISTERED'] = true;
        }

        if ($this->shouldDumpContext()) {
            $this->dumpContext();
        }
    }

    /**
     * Enregistre une étape manuelle dans le scénario de test (core).
     */
    protected function logStep(string $description): void
    {
        $this->addContextStep(false, $description);
    }

    /**
     * Ajoute une étape au contexte courant.
     *
     * @param object|null $client Instance du client (pour extensions Symfony : Session/Profiler).
     */
    protected function addContextStep(bool $fullDump = false, ?string $description = null, ?object $request = null, ?object $response = null, ?object $client = null): void
    {
        $requestSummary = null;
        $responseCode = null;

        if (!$client && property_exists(static::class, 'client')) {
            $client = static::$client ?? null;
        }

        try {
            if ($request && method_exists($request, 'getMethod')) {
                $uri = method_exists($request, 'getRequestUri') ? $request->getRequestUri() : (method_exists($request, 'getUri') ? $request->getUri() : '');
                $requestSummary = sprintf('%s %s', $request->getMethod(), $uri);
            } elseif ($client && method_exists($client, 'getRequest') && $req = $client->getRequest()) {
                $uri = method_exists($req, 'getRequestUri') ? $req->getRequestUri() : (method_exists($req, 'getUri') ? $req->getUri() : '');
                $requestSummary = sprintf('%s %s', $req->getMethod(), $uri);
                $request = $req;
            }

            if ($response && method_exists($response, 'getStatusCode')) {
                $responseCode = $response->getStatusCode();
            } elseif ($client && method_exists($client, 'getResponse') && $resp = $client->getResponse()) {
                $responseCode = method_exists($resp, 'getStatusCode') ? $resp->getStatusCode() : null;
                $response = $resp;
            }
        } catch (\Throwable $e) {
        }

        $extraData = [];

        if ($this->shouldDumpContext() || $fullDump) {
            $captureTime = (new \DateTime())->format('H:i:s.u');
            $context = [
                'full_dump' => $fullDump,
                'request' => $request,
                'response' => $response,
                'client' => $client,
                'container' => method_exists($this, 'getContainer') ? static::getContainer() : null,
                'logs' => $this->getLogsForContext($client),
                'capture_time' => $captureTime,
            ];

            $extraData = $this->getContextSnapshotter()->collect($context);
        }

        $this->getTraceTracker()->addStep(
            $description ?? 'Snapshot',
            $requestSummary,
            $responseCode,
            $extraData
        );
    }

    /**
     * Retourne les logs à inclure dans le contexte (core : vide ; Symfony override avec getSymfonyLogs).
     */
    protected function getLogsForContext(?object $client = null): array
    {
        return [];
    }

    protected function shouldDumpContext(): bool
    {
        $reflection = null;
        try {
            $testName = method_exists($this, 'name') ? $this->name() : $this->getName(false);
            $reflection = new \ReflectionMethod($this, $testName);
        } catch (\Throwable $e) {
        }

        return $this->getDumpDecisionMaker()->decide($reflection, $this->hasFailed());
    }

    protected function dumpContext(): void
    {
        if ($this->contextDumpDone) {
            return;
        }
        $this->contextDumpDone = true;

        $this->addContextStep(true, 'Final State (Dump)');

        // Chemin : CONTEXT_TEST_OUTPUT_PATH (env, défini par TestBootstrapper via TestKernel) > CONTEXT_TEST_OUTPUT_DIR > défaut
        $filename = 'var/log/phpunit.datacontext.txt';
        $path = getenv('CONTEXT_TEST_OUTPUT_PATH') ?: ($_ENV['CONTEXT_TEST_OUTPUT_PATH'] ?? $_SERVER['CONTEXT_TEST_OUTPUT_PATH'] ?? '');
        if ($path !== '') {
            $filename = $path;
        } else {
            $dir = defined('CONTEXT_TEST_OUTPUT_DIR') ? CONTEXT_TEST_OUTPUT_DIR : (getenv('CONTEXT_TEST_OUTPUT_DIR') ?: ($_ENV['CONTEXT_TEST_OUTPUT_DIR'] ?? $_SERVER['CONTEXT_TEST_OUTPUT_DIR'] ?? ''));
            if ($dir !== '') {
                $filename = rtrim($dir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . sprintf('phpunit.datacontext-%s.txt', getmypid() ?: uniqid());
            }
        }

        $testName = method_exists($this, 'name') ? $this->name() : $this->getName(false);

        $report = [
            'test_class' => static::class,
            'test_name' => $testName,
            'status' => $this->hasFailed() ? 'FAILED' : 'PASSED',
            'data_set' => method_exists($this, 'dataName') ? $this->dataName() : null,
            'date' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            'steps' => $this->getTraceTracker()->getSteps(),
        ];

        $content = $this->getReportGenerator()->generate($report);
        $this->getReportGenerator()->save($content, $filename);

        echo "D";
    }

    protected function hasFailed(): bool
    {
        // PHPUnit 10+: status() retourne TestStatus avec isFailure()/isError()
        if (method_exists($this, 'status')) {
            $status = $this->status();
            return $status->isFailure() || $status->isError();
        }
        // PHPUnit 9: getStatus() retourne une constante BaseTestRunner
        if (method_exists($this, 'getStatus')) {
            $status = $this->getStatus();
            return $status === BaseTestRunner::STATUS_FAILURE || $status === BaseTestRunner::STATUS_ERROR;
        }

        return false;
    }
}
