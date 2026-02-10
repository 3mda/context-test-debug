# context-test-debug

Capture test context (DB, logs, session, cookies) to debug PHPUnit tests.

When a test fails—or when you ask for it—this library captures a snapshot of your application context at each step and writes a report so you can debug without re-running blindly.

**Requirements:** PHP 8.0+, PHPUnit 9.5+ / 10+, and optionally Symfony 6+ / 7+ for the Symfony bridge.

**Package layout (namespace `ContextTest\`):**
- **Core** — `src/Bridge/` (EnableContextDump, PHPUnit/ContextAwareTestTrait), `src/Context/` (PhpErrorLogBuffer, ContextSnapshotter, Collector/, Decision/, Report/, State/)
- **Symfony bridge** — `src/Symfony/Bridge/` (Client/, Log/, MonologTestLogPass, TestKernelTrait, PHPUnit/ContextAwareTestTrait, TestBootstrapper), `src/Symfony/Context/` (Collector/, ContextSnapshotter)

---

## Installation

```bash
composer require 3mda/context-test-debug --dev
```

---

## Quick start

### 1. Bootstrap (e.g. in `tests/bootstrap.php`)

```php
<?php

use ContextTest\Bridge\PHPUnit\TestBootstrapper;

require dirname(__DIR__) . '/vendor/autoload.php';

TestBootstrapper::bootstrap();
```

### 2. Use the trait in your test

```php
<?php

use ContextTest\Bridge\PHPUnit\ContextAwareTestTrait;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    use ContextAwareTestTrait;

    public function test_something(): void
    {
        $this->logStep('Starting my scenario');
        // Your test logic; context is captured on failure or when you force a dump.
        $this->assertTrue(true);
    }
}
```

The trait hooks into PHPUnit's lifecycle: it records key steps (requests, form submissions, etc.) and, on failure or when you force it, captures whatever context your collectors provide and writes a report. If you use the **Symfony bridge**, you also get `createTestClient()` to wrap your HTTP client and capture browser, session, DB, and logs—see [With Symfony](#with-symfony) below.

### 3. Run tests

- **On failure:** a context report is generated automatically.
- **On success:** no report unless you force it (see below).

---

## Console output: the **D** indicator

By default, context reports are only written on **failure or error**, so output and disk stay clean.

**Custom indicator:** When a context report is generated, PHPUnit's output includes a **`D`** (Dump): a full context report was written to help you debug.

**Order of output:** The dump runs during the test (in `tearDown` or when an exception is caught), while PHPUnit prints the test result (`.`, `F`, `E`) at the very end. So **`D` always appears before** the final result.

**Summary:**

| Output | Meaning |
|--------|--------|
| `.` | Success (no dump, unless forced → see below) |
| `D.` | Success + dump (e.g. forced via `#[EnableContextDump]` or `DEBUG=1` / `DEBUG=true`) |
| `DF` | Failure + dump generated for debugging |
| `DE` | Error + dump generated for debugging |
| `S` | Skipped (no dump) |
| `I` | Incomplete (no dump) |
| `R` | Risky (no dump) |

Reports are written under a configurable path (e.g. `var/log/` with the Symfony setup). Each report contains, per step: action name, timestamp, and whatever your collectors provide (browser, session, DB, logs, etc.).

---

## When is a report generated?

1. **Test failed or error** → report is generated (unless `DEBUG` is falsy; see below).
2. **Attribute on the test method** → report is generated even when the test passes:
   ```php
   use ContextTest\Bridge\EnableContextDump;

   #[EnableContextDump]
   public function test_audit_this_flow(): void
   {
       // This test will produce a report (D) even if it passes.
   }
   ```
3. **Environment variable** → when `DEBUG` is **truthy**, all tests produce a report; when **falsy**, the module is disabled (no dump, including on failure—e.g. to debug the package itself):
   ```bash
   # Enable (any case): 1, true, yes, on
   DEBUG=1 vendor/bin/phpunit
   DEBUG=true vendor/bin/phpunit
   # Disable: 0, false, no, off
   DEBUG=0 vendor/bin/phpunit
   DEBUG=false vendor/bin/phpunit
   ```
   The variable name is case-insensitive (`DEBUG`, `debug`, `Debug` all work).

### How `#[EnableContextDump]` interacts with `DEBUG`

The decision order is: **(1) DEBUG falsy → (2) test failed → (3) DEBUG truthy → (4) attribute on method.**

| Situation | Effect |
|-----------|--------|
| **DEBUG not set** | Dump on failure, or for methods with the attribute when the test passes. |
| **DEBUG=1 / true / yes / on** | Dump for **all** tests (failure or pass). |
| **DEBUG=0 / false / no / off** | **No dump.** Module disabled for the whole run (including on failure), e.g. to debug the package itself. |

So when DEBUG is **falsy**, it takes precedence over everything: no dump, even on failure or on methods with the attribute.

---

## Running the package tests with debug

When you run the **package’s own** PHPUnit tests (e.g. from `vendor/3mda/context-test-debug/`), the trait is already used and the bootstrap:

- Sets **`CONTEXT_TEST_OUTPUT_DIR`** to the package’s `var/log/`, so context reports are written under the package (e.g. `vendor/3mda/context-test-debug/var/log/phpunit.datacontext-&lt;pid&gt;.txt`).
- Registers an **error handler** so PHP errors (warnings, notices, etc.) are captured in the report (PhpErrorLogBuffer).

So:

- **On failure** → a report is generated automatically in the package’s `var/log/`.
- **On success** → no report unless you force it:
  - `DEBUG=1` or `DEBUG=true` (or `debug=1`, etc.; variable is case-insensitive), or
  - `#[EnableContextDump]` on the test method.

Example from the host project root:

```bash
DEBUG=1 vendor/bin/phpunit vendor/3mda/context-test-debug/tests
```

Reports will appear under `vendor/3mda/context-test-debug/var/log/` (this directory is in `.gitignore`).

---

## What is captured?

What you get depends on which collectors you use. The **core** only defines the contract; **bridges** provide concrete collectors.

- **Steps:** each tracked action (e.g. request, submit, redirect) with timestamp and memory—always available when using the trait.
- **Custom context:** whatever your own collectors put in the context bag (e.g. app state, fixtures).

With the **Symfony bridge** you additionally get:

- **Browser:** request URL, status code, and optionally response content (configurable).
- **Session:** session ID, attributes, flash messages.
- **Cookies:** name, path, domain, value (truncated if long).
- **Database:** dump of configured entities (DatabaseCollector).
- **Logs:** Monolog/Symfony logs collected during the request.

Report format and output path are configurable. With the Symfony bridge, reports are typically written as YAML under `var/log/`.

---

## With Symfony

If you use Symfony and `WebTestCase`, the bridge adds a tracked client so each request is captured:

```php
<?php

use ContextTest\Bridge\PHPUnit\ContextAwareTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MyWebTest extends WebTestCase
{
    use ContextAwareTestTrait;

    public function test_something(): void
    {
        $client = $this->createTestClient();  // tracked: each action is snapshotted
        $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
    }
}
```

You get collectors for browser (URL, status, response), session, cookies, database, logs, and mailer. Other frameworks (e.g. Laravel) can be supported later via their own bridges.

**If your test overrides `tearDown()`**, call `$this->runContextAwareTearDown()` at the start of your tearDown so the dump logic still runs on failure:

```php
protected function tearDown(): void
{
    $this->runContextAwareTearDown();
    // your cleanup...
    parent::tearDown();
}
```

### Symfony Profiler in test

The bootstrap sets **`APP_PROFILER_COLLECT_IN_TEST=0`** so that `framework.profiler.collect` is false in test.

**Known Symfony/Doctrine bug:** `DoctrineDataCollector` accesses `$this->data['queries']` without checking if the key exists. After `reset()` or when `collect()` was never called, `$this->data` is empty → `Undefined array key "queries"` (DoctrineDataCollector.php, lines 137, 146, 155). Solution: disable profiler collection in test. See [DoctrineDataCollector source](https://github.com/symfony/doctrine-bridge/blob/6.4/DataCollector/DoctrineDataCollector.php).

---

## Architecture (overview)

- **Core:** framework-agnostic. Collectors implement a simple contract: `collect(array $context): array`. A snapshotter aggregates them and a report generator writes the result.
- **Bridge PHPUnit:** `ContextAwareTestTrait`, `TestBootstrapper`, `EnableContextDump` attribute. Wires context capture into the test lifecycle.
- **Bridge Symfony:** optional. Provides `TestKernel`, a traceable client wrapper, in-memory log handler, and collectors that read from Symfony's request/response/container (Browser, Session, Database, Log, Mailer, Query).

You can use the core with only the PHPUnit bridge and your own collectors; the Symfony bridge is optional for deeper integration.

---

## Development and sharing

The package lives in its own Git repo and is designed to be reusable across projects.

You can clone or develop this package pull requests are welcome.

**Using the package from the repo**

In your project's `composer.json`, add the VCS repository and require the package:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/3mda/context-test-debug"
        }
    ],
    "require-dev": {
        "3mda/context-test-debug": "^1.0"
    }
}
```

Then:

- **`composer install`** / **`composer update`** installs or updates the package into `vendor/3mda/context-test-debug`.

---

## License

Apache-2.0. See [LICENSE](LICENSE) for details.
