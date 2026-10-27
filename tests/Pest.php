<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Api\AwaitableWebpage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A browser script that lists every Content-Security-Policy violation of the page so far, as JSON.
 * Use it as `->assertScript(cspViolations(), '[]')`. The Reporting API buffers the reports raised
 * before the observer existed, so violations during page load are caught too.
 */
function cspViolations(): string
{
    return '() => new Promise((resolve) => {
        const reports = [];
        const observer = new ReportingObserver((found) => reports.push(...found), { types: ["csp-violation"], buffered: true });
        observer.observe();
        setTimeout(() => {
            reports.push(...observer.takeRecords());
            resolve(JSON.stringify(reports.map(({ body }) => [body.effectiveDirective, body.blockedURL, body.sample])));
        }, 100);
    })';
}

/**
 * Opens a page in the browser and checks it is healthy: the rendered Inertia component is exactly
 * `$component`, with no JavaScript errors, no CSP violations and no accessibility issues (axe, level 1).
 * `$reach` runs the steps that lead from `$url` to the page under check (fill a form, open a menu…).
 * Every Inertia page needs one of these tests: tests/Unit/BrowserCoverageTest.php enforces it.
 *
 * @param  (Closure(AwaitableWebpage): mixed)|null  $reach
 */
function visitPage(string $component, string $url, bool $dark = false, bool $mobile = false, ?Closure $reach = null): AwaitableWebpage
{
    $pending = $mobile ? visit($url)->on()->mobile() : visit($url);
    $pending = $dark ? $pending->inDarkMode() : $pending->inLightMode();

    // Touching the pending page opens it; a plain assertion returns the real page to keep chaining on.
    $page = $pending->assertNoJavaScriptErrors();

    if ($reach !== null) {
        $reach($page);
    }

    // Inertia keeps the current page object in the history state; before it boots, only in the initial <script data-page>.
    return $page->assertScript('() => (window.history.state?.page ?? JSON.parse(document.querySelector("script[data-page]").textContent)).component', $component)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        // axe reads colors as painted: let fade-ins finish so it judges the settled page, not a frame mid-transition
        // (endless ones, like the OTP caret blink, never finish and are left alone).
        ->assertScript('() => Promise.all(document.getAnimations().filter((animation) => animation.effect?.getTiming().iterations !== Infinity).map((animation) => animation.finished)).then(() => true)')
        ->assertNoAccessibilityIssues(1);
}
