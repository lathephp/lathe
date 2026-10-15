<?php

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Lathe\Http\PanelRoutes;
use Lathe\Tests\TestCase;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Pest\Browser\Playwright\InitScript;
use Pest\Browser\Playwright\Playwright;
use Pest\Browser\Support\ComputeUrl;
use Pest\Expectation;
use Workbench\Database\Seeders\DemoSeeder;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('browser')
    ->beforeEach(function () {
        config(['workbench.strict_csp' => true]);
        $this->seed(DemoSeeder::class);
    })
    ->in('Browser');

/**
 * Register the panel routes again after a test changes lathe.path or lathe.domain.
 */
function reloadLatheRoutes(): void
{
    $router = app('router');
    $router->setRoutes(new RouteCollection);
    app(PanelRoutes::class)->register();
    $router->getRoutes()->refreshNameLookups();
}

/**
 * SQL text written with "double quoted" identifiers, quoted the way the test database
 * quotes them: MySQL and MariaDB use backticks.
 */
function quotedSql(string $sql): string
{
    return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
        ? str_replace('"', '`', $sql)
        : $sql;
}

/**
 * Remove a gate ability, for tests of what happens when the app never defined it.
 */
function forgetGate(string $ability): void
{
    $gate = app(Gate::class);
    $abilities = (fn () => $this->abilities)->call($gate);
    unset($abilities[$ability]);
    (fn () => $this->abilities = $abilities)->call($gate);
}

/**
 * The browser projects one run covers: the one LATHE_BROWSER_PROJECT names, else
 * desktop Chrome and the Android phone.
 *
 * @return list<string>
 */
function browserProjects(): array
{
    $project = getenv('LATHE_BROWSER_PROJECT');

    return is_string($project) && $project !== '' ? [$project] : ['desktop-chrome', 'phone-android'];
}

/**
 * Open a panel URL in the project's device, with a hook that records CSP violations,
 * console.error calls and unhandled rejections from the first script on.
 */
function latheVisit(string $url, string $project): AwaitableWebpage
{
    $pending = visit($url);

    $on = match ($project) {
        'phone-android' => $pending->on()->mobile(),
        'phone-ios' => $pending->on()->iPhone14Pro(),
        default => null,
    };

    // On opens a new page on every call, so the device is kept in one pending page.
    if ($on !== null) {
        $pending = (fn () => new PendingAwaitablePage($this->browserType, $this->device, $this->url, $this->options))->call($on);
    }

    // The plugin's own hook sees only console.log and window errors. Ours joins the plugin's
    // init script on a context built the way the plugin builds one, so the page loads once
    // with both: a second load raced the first in Firefox, which reports a load done early.
    [$browserType, $device, $options] = (fn () => [$this->browserType, $this->device, $this->options])->call($pending);
    $context = Playwright::browser($browserType)->launch()->newContext([
        'locale' => 'en-US',
        'timezoneId' => 'UTC',
        'colorScheme' => Playwright::defaultColorScheme()->value,
        ...$device->context(),
        ...$options,
    ]);
    $context->addInitScript(InitScript::get());
    $context->addInitScript(<<<'JS'
        window.__lathe = { csp: [], errors: [] };
        document.addEventListener('securitypolicyviolation', (event) => {
            window.__lathe.csp.push(`${event.effectiveDirective} blocked ${event.blockedURI || 'inline'}`);
        });
        const consoleError = console.error;
        console.error = (...args) => {
            window.__lathe.errors.push(args.map(String).join(' '));
            consoleError.apply(console, args);
        };
        window.addEventListener('unhandledrejection', (event) => {
            window.__lathe.errors.push(`unhandled rejection: ${String(event.reason)}`);
        });
        JS);
    $address = ComputeUrl::from($url);
    $page = $context->newPage()->goto($address, $options);

    // Firefox returns from goto, and from waitForFunction, while the tab still shows
    // about:blank, so this asks until the document has arrived and parsed.
    $deadline = microtime(true) + 10;
    do {
        try {
            if ($page->evaluate("location.href !== 'about:blank' && document.readyState !== 'loading'") === true) {
                break;
            }
        } catch (Throwable) {
            // The document changed under the question; ask again.
        }
        usleep(50_000);
    } while (microtime(true) < $deadline);

    return new AwaitableWebpage($page, $address);
}

/**
 * A key combination with the modifier the panel listens for: Meta where navigator.platform
 * says Mac, as it does in Playwright's WebKit, else Control.
 */
function modKey(AwaitableWebpage|Webpage $page, string $keys): string
{
    return ($page->script('/mac/i.test(navigator.platform)') === true ? 'Meta+' : 'Control+').$keys;
}

/**
 * A CSS value expression with its numbers rounded to three decimals: WebKit prints six
 * where Chromium and Firefox print what the stylesheet says.
 */
function roundedCss(string $expression): string
{
    return "({$expression}).replace(/\\d+\\.\\d+/g, (number) => String(Math.round(number * 1000) / 1000))";
}

/**
 * Assert the page met no CSP violation, no JavaScript error and no console.error call.
 */
function assertCleanPage(AwaitableWebpage|Webpage $page): void
{
    $page->assertNoJavaScriptErrors();

    expect($page->script('window.__lathe.csp'))->toBe([], 'CSP violations')
        ->and($page->script('window.__lathe.errors'))->toBe([], 'console errors');
}

/**
 * Assert the page fits its viewport with no horizontal page scroll.
 */
function assertFitsViewport(AwaitableWebpage|Webpage $page): void
{
    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue('horizontal page scroll');
}

/**
 * A cookie the browser holds, as Playwright reports it (name, value, path, expires, sameSite).
 *
 * @return array<string, mixed>|null
 */
function browserCookie(AwaitableWebpage $page, string $name): ?array
{
    $context = (fn () => $this->page)->call($page)->context();

    foreach ((fn () => $this->sendMessage('cookies', ['urls' => []]))->call($context) as $message) {
        foreach ($message['result']['cookies'] ?? [] as $cookie) {
            if ($cookie['name'] === $name) {
                return $cookie;
            }
        }
    }

    return null;
}

/**
 * Sign in through the login form the page shows.
 */
function signInThroughForm(AwaitableWebpage|Webpage $page, string $email, string $password = 'password'): void
{
    $page->fill('email', $email)
        ->fill('password', $password)
        ->press('Sign in');
}

/**
 * Hold the build lock until the test ends. Test files run in parallel processes, and the ones
 * that run pnpm build or read dist would otherwise write the same dist folders under each other.
 */
function holdBuildLock(): void
{
    $handle = fopen(sys_get_temp_dir().'/lathe-tests-build.lock', 'c');

    if ($handle === false) {
        throw new RuntimeException('Cannot open the build lock.');
    }

    flock($handle, LOCK_EX);
    test()->beforeApplicationDestroyed(function () use ($handle) {
        flock($handle, LOCK_UN);
        fclose($handle);
    });
}

/**
 * A JSON column's value with object keys sorted at every level: MySQL stores keys in its own
 * order, so a stored row compares by content, list order and types, not key order.
 */
function keysSorted(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $value = array_map(keysSorted(...), $value);

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

expect()->extend('toMatchStoredJson', function (mixed $expected) {
    /** @var Expectation<mixed> $this */
    expect(keysSorted($this->value))->toBe(keysSorted($expected));

    return $this;
});

/**
 * Opens the panel home once, so a lazy sidebar badge (the workbench Insights plugin counts
 * open orders) is cached for the user and a query budget counts the page's own queries.
 */
function warmNavigationBadges(): void
{
    test()->get('/lathe')->assertOk();
}
