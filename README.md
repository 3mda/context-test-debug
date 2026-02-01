# context-test-debug

Capture test context (DB, logs, session, cookies) to debug PHPUnit tests.

When a test fails—or when you ask for it—this library captures a snapshot of your application context at each step and writes a report so you can debug without re-running blindly.

**Requirements:** PHP 8.0+, PHPUnit 9.5+ / 10+, and optionally Symfony 6+ / 7+ for the Symfony bridge.

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

The trait hooks into PHPUnit’s lifecycle: it records key steps (requests, form submissions, etc.) and, on failure or when you force it, captures whatever context your collectors provide and writes a report. If you use the **Symfony bridge**, you also get `createTestClient()` to wrap your HTTP client and capture browser, session, DB, and logs—see [With Symfony](#with-symfony) below.

### 3. Run tests

- **On failure:** a context report is generated automatically.
- **On success:** no report unless you force it (see below).

---

## Console output: the **D** indicator

By default, context reports are only written on **failure or error**, so output and disk stay clean.

**Custom indicator:** When a context report is generated, PHPUnit’s output includes a **`D`** (Dump): a full context report was written to help you debug.

**Order of output:** The dump runs during the test (in `tearDown` or when an exception is caught), while PHPUnit prints the test result (`.`, `F`, `E`) at the very end. So **`D` always appears before** the final result.

**Summary:**

| Output | Meaning |
|--------|--------|
| `.` | Success (no dump, unless forced → see below) |
| `D.` | Success + dump (e.g. forced via `#[EnableContextDump]` or `TEST_FORCE_LOGS=1`) |
| `DF` | Failure + dump generated for debugging |
| `DE` | Error + dump generated for debugging |
| `S` | Skipped (no dump) |
| `I` | Incomplete (no dump) |
| `R` | Risky (no dump) |

Reports are written under a configurable path (e.g. `var/log/` with the Symfony setup). Each report contains, per step: action name, timestamp, and whatever your collectors provide (browser, session, DB, logs, etc.).

---

## When is a report generated?

1. **Test failed or error** → report is generated.
2. **Attribute on the test method** → report is generated even when the test passes:
   ```php
   use ContextTest\Bridge\EnableContextDump;

   #[EnableContextDump]
   public function test_audit_this_flow(): void
   {
       // This test will produce a report (D) even if it passes.
   }
   ```
3. **Environment variable** → all tests produce a report:
   ```bash
   TEST_FORCE_LOGS=1 vendor/bin/phpunit
   ```

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

---

## Architecture (overview)

- **Core:** framework-agnostic. Collectors implement a simple contract: `collect(array $context): array`. A snapshotter aggregates them and a report generator writes the result.
- **Bridge PHPUnit:** `ContextAwareTestTrait`, `TestBootstrapper`, `EnableContextDump` attribute. Wires context capture into the test lifecycle.
- **Bridge Symfony:** optional. Provides `TestKernel`, a traceable client wrapper, in-memory log handler, and collectors that read from Symfony’s request/response/container (Browser, Session, Database, Log, Mailer, Query).

You can use the core with only the PHPUnit bridge and your own collectors; the Symfony bridge is optional for deeper integration.

---

## License

Apache-2.0. See [LICENSE](LICENSE) for details.
