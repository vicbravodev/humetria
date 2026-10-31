<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantContext;
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

function superadmin(): User
{
    $user = User::factory()->create();
    // The platform role belongs to no company: assigned outside any team.
    setPermissionsTeamId(null);
    $user->assignRole(User::SUPERADMIN);

    return $user;
}

function signInAsSuperadmin(): void
{
    test()->actingAs(superadmin());
}

function ownerOf(Tenant $tenant): User
{
    $user = User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($user)->create();

    return $user;
}

/**
 * Makes `$tenant` the company the code under test works in, as ResolveTenant does for a request inside it.
 */
function workIn(Tenant $tenant): void
{
    resolve(TenantContext::class)->set($tenant);
}

/** The company of a row of another module, which knows it only by its id. */
function tenantOf(string $tenantId): Tenant
{
    return Tenant::query()->findOrFail($tenantId);
}

/** A new user who belongs to `$tenant` with `$role`. */
function memberOf(Tenant $tenant, TenantRole $role): User
{
    $user = User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($user)->role($role)->create();

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function workCenterForm(array $overrides = []): array
{
    return [
        'name' => 'Planta Querétaro',
        'street' => 'Av. 5 de Febrero',
        'exterior_number' => '1200',
        'interior_number' => 'Nave 3',
        'neighborhood' => 'Zona Industrial Benito Juárez',
        'postal_code' => '76120',
        'municipality' => 'Querétaro',
        'state' => 'QUE',
        'main_activity' => 'Manufactura de autopartes',
        ...$overrides,
    ];
}

/** The work center as stored now, read past the company scope. */
function reloaded(WorkCenter $workCenter): WorkCenter
{
    return WorkCenter::query()->withoutGlobalScopes()->findOrFail($workCenter->id);
}

/**
 * Signs in a user (a new one by default) who belongs to the company at /admin/acme and returns that URL:
 * /admin itself sends a person to their company, so the pages inside a company are reached from there.
 */
function signInToPanel(?User $user = null): string
{
    $user ??= User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['slug' => 'acme']))->for($user)->create();
    test()->actingAs($user);

    return '/admin/acme';
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

// English words the starter kit showed and that never appear in Spanish UI text: any of them on a page means untranslated text.
const ENGLISH_STOPLIST = [
    'the', 'your', 'you', 'and', 'with', 'please', 'password', 'account', 'settings', 'profile', 'appearance',
    'dashboard', 'log', 'login', 'sign', 'forgot', 'remember', 'save', 'cancel', 'delete', 'confirm', 'enable',
    'disable', 'code', 'codes', 'recovery', 'continue', 'back', 'email', 'name', 'close', 'light', 'dark', 'system',
    'platform', 'repository', 'documentation', 'security', 'toggle', 'sidebar', 'loading', 'show', 'hide', 'more',
    'passkey', 'passkeys', 'verify', 'verification', 'update', 'reset', 'register', 'create', 'already', 'have',
    'enter', 'address', 'warning', 'navigation', 'menu', 'two', 'authentication', 'add', 'remove', 'manage',
    'search', 'this', 'are', 'will', 'here', 'click', 'link', 'new', 'current', 'full',
];

/**
 * A browser script that lists, as JSON, the stoplisted English words the page shows: its visible text,
 * the accessible names it gives through aria-label, title, placeholder and alt, and the document title.
 * Words match whole and case-insensitive; letters with accents count as letters, so "código" is not "code".
 */
function englishWords(): string
{
    $words = json_encode(ENGLISH_STOPLIST, JSON_THROW_ON_ERROR);

    return '() => {
        const attributes = [...document.querySelectorAll("[aria-label], [title], [placeholder], [alt]")]
            .flatMap((element) => ["aria-label", "title", "placeholder", "alt"].map((name) => element.getAttribute(name) ?? ""));
        const text = [document.title, document.body.innerText, ...attributes].join("\n");
        const pattern = new RegExp("(?<![\\\\p{L}\\\\p{N}])(" + '.$words.'.join("|") + ")(?![\\\\p{L}\\\\p{N}])", "giu");

        return JSON.stringify([...new Set([...text.matchAll(pattern)].map((match) => match[0].toLowerCase()))].sort());
    }';
}

/**
 * Opens a page in the browser and checks it is healthy: the rendered Inertia component is exactly
 * `$component`, with no JavaScript errors, no CSP violations, no accessibility issues (axe, level 1)
 * and no English left in what it shows (see ENGLISH_STOPLIST).
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
    $page->assertScript('() => (window.history.state?.page ?? JSON.parse(document.querySelector("script[data-page]").textContent)).component', $component);

    $page->assertScript(englishWords(), '[]');

    return $page->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        // axe reads colors as painted: let fade-ins finish so it judges the settled page, not a frame mid-transition
        // (endless ones, like the OTP caret blink, never finish and are left alone).
        ->assertScript('() => Promise.all(document.getAnimations().filter((animation) => animation.effect?.getTiming().iterations !== Infinity).map((animation) => animation.finished)).then(() => true)')
        ->assertNoAccessibilityIssues(1);
}

/**
 * Resizes the window and waits until React has re-rendered for the new width (the sidebar becomes a sheet below
 * 768 px), so what the next assertion measures is the layout at that width, not the one before.
 */
function resizeTo(AwaitableWebpage $page, int $width, int $height = 900): AwaitableWebpage
{
    $page->resize($width, $height)
        ->assertScript('() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve(true))))');

    return $page;
}

/**
 * A complete NOM-035 Guía II or III with every item answered `$value`, and its two gates answered as given: a "no"
 * leaves the block's items out, as the survey does.
 *
 * @return array<string, string>
 */
function nom035Answers(string $key, string $value = 'sometimes', bool $clients = true, bool $supervises = true): array
{
    [$base, $clientItems, $supervisionItems] = $key === 'nom035.guide-ii'
        ? [range(1, 40), range(41, 43), range(44, 46)]
        : [range(1, 64), range(65, 68), range(69, 72)];
    $items = [...$base, ...($clients ? $clientItems : []), ...($supervises ? $supervisionItems : [])];
    $answers = [];

    foreach ($items as $number) {
        $answers[(string) $number] = $value;
    }

    // Assigned, never spread: a spread renumbers the numeric item keys.
    $answers['serves_clients'] = $clients ? 'yes' : 'no';
    $answers['supervises'] = $supervises ? 'yes' : 'no';

    return $answers;
}

/**
 * A complete Guía I with "yes" on the items given and "no" on the rest. With no "yes" in section I (items 1 to 6) it
 * ends there, as GR.I a) says.
 *
 * @param  list<int>  $yes
 * @return array<string, string>
 */
function guideOneAnswers(array $yes = []): array
{
    $last = array_intersect($yes, range(1, 6)) === [] ? 6 : 20;
    $answers = [];

    foreach (range(1, $last) as $number) {
        $answers[(string) $number] = in_array($number, $yes, true) ? 'yes' : 'no';
    }

    return $answers;
}

/**
 * Active people in a work center of `$tenant`, in one area, by gender: `$female` women, `$male` men and `$other` who
 * answered "Otro / prefiero no decir".
 *
 * @return Illuminate\Support\Collection<int, App\Modules\Organization\Models\Employee>
 */
function staffOf(Tenant $tenant, WorkCenter $workCenter, int $female, int $male = 0, int $other = 0): Illuminate\Support\Collection
{
    $area = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id]);
    $people = collect();

    foreach (['female' => $female, 'male' => $male, 'other' => $other] as $gender => $count) {
        if ($count > 0) {
            $people = $people->concat(App\Modules\Organization\Models\Employee::factory()->count($count)->create([
                'tenant_id' => $tenant->id,
                'work_center_id' => $workCenter->id,
                'area_id' => $area->id,
                'gender' => $gender,
            ]));
        }
    }

    return $people;
}
