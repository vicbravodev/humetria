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
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

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

// A browser script that holds every request of the page 400 ms before it leaves, so a test can see the page mid-request.
const SLOW_REQUESTS = '() => { const send = XMLHttpRequest.prototype.send; XMLHttpRequest.prototype.send = function (...args) { setTimeout(() => send.apply(this, args), 400); }; }';

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

    $page->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        // axe reads colors as painted: let fade-ins finish so it judges the settled page, not a frame mid-transition
        // (endless ones, like the OTP caret blink, never finish and are left alone).
        ->assertScript('() => Promise.all(document.getAnimations().filter((animation) => animation.effect?.getTiming().iterations !== Infinity).map((animation) => animation.finished)).then(() => true)');

    if ($mobile) {
        $page->assertScript(smallTouchTargets(), '');
    }

    return $page->assertNoAccessibilityIssues(1);
}

/**
 * A script that names the controls a finger can hardly hit: less than 44 px tall, counting the invisible area a
 * pseudo-element (`touch-hit`, the small buttons) or its input group adds around them. Left out, as WCAG 2.5.8 leaves
 * them: a link or button inside a sentence, and a checkbox or radio whose label also toggles it. Empty when every control is big enough.
 */
function smallTouchTargets(): string
{
    return <<<'JS'
        () => {
            const reach = (element) => {
                const box = element.getBoundingClientRect();
                // A field and its button inside an input group take the touch of the whole group.
                let height = Math.max(box.height, element.closest('[data-slot="input-group"]')?.getBoundingClientRect().height ?? 0);
                for (const pseudo of ['::before', '::after']) {
                    const style = getComputedStyle(element, pseudo);
                    if (style.content !== 'none' && style.position === 'absolute') {
                        height = Math.max(height, box.height - (parseFloat(style.top) || 0) - (parseFloat(style.bottom) || 0));
                    }
                }
                return height;
            };
            const labelled = (element) => element.closest('label') !== null || (element.id !== '' && document.querySelector(`label[for="${element.id}"]`) !== null);

            return Array.from(document.querySelectorAll('a[href], button, summary, [role="tab"], [role="combobox"], input:not([type="hidden"]), select, textarea')).filter((element) => {
                const box = element.getBoundingClientRect();
                if (box.width <= 1 || box.height <= 1 || getComputedStyle(element).visibility === 'hidden' || element.closest('[aria-hidden="true"], [inert]')) return false;
                if (element.matches('a, button') && Array.from(element.parentElement.childNodes).some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '')) return false;
                if ((element.matches('[role="checkbox"], [role="radio"], input[type="checkbox"], input[type="radio"]')) && labelled(element)) return false;
                return reach(element) < 43.5;
            }).map((element) => `${element.getAttribute('aria-label') ?? element.textContent.trim().slice(0, 40)} (${Math.round(reach(element))} px)`).join(' | ');
        }
        JS;
}

/**
 * Resizes the window and waits until React has re-rendered for the new width (the sidebar becomes a sheet below
 * 768 px) and its transitions have settled, so the next assertion measures the layout at that width.
 */
function resizeTo(AwaitableWebpage $page, int $width, int $height = 900): AwaitableWebpage
{
    $page->resize($width, $height)
        // Settled once the page keeps the same width and nothing is in transition for five frames in a row.
        ->assertScript(<<<'JS'
            () => new Promise((resolve) => {
                let last = '';
                let still = 0;
                const frame = () => {
                    const now = Array.from(document.querySelectorAll('[data-slot="sidebar-inset"], main')).map((box) => box.getBoundingClientRect().width).join();
                    const moving = document.getAnimations().some((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations !== Infinity);
                    still = now === last && ! moving ? still + 1 : 0;
                    last = now;
                    still >= 5 ? resolve(true) : requestAnimationFrame(frame);
                };
                requestAnimationFrame(frame);
            })
            JS);

    return $page;
}

/**
 * A script that names what spills past the edges of the page's panel (or the window, outside the panel): an element
 * cut off at the side, unless it sits in a box that scrolls sideways on purpose, as a wide table does. Empty when
 * everything fits.
 */
function spilledOut(): string
{
    return <<<'JS'
        () => {
            const panel = document.querySelector('[data-slot="sidebar-inset"]');
            const scrolls = (element) => {
                for (let box = element.parentElement; box; box = box.parentElement) {
                    if (['auto', 'scroll'].includes(getComputedStyle(box).overflowX)) return true;
                }
                return false;
            };

            return Array.from(document.querySelectorAll('body *')).filter((element) => {
                const box = element.getBoundingClientRect();
                const edge = panel?.contains(element) ? panel.getBoundingClientRect() : { left: 0, right: window.innerWidth };
                return box.width > 0 && box.height > 0 && (box.right > edge.right + 1 || box.left < edge.left - 1) && ! scrolls(element) && ! element.closest('[aria-hidden="true"]');
            }).map((element) => element.getAttribute('aria-label') ?? element.textContent.trim().slice(0, 30)).join(' | ');
        }
        JS;
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

/**
 * Fails when the props of a page hold an individual response, whatever the key it hides under: the id of a stored
 * anonymous response, a map named `answers` (a count by that name passes), or an answer given to an item (an item number or a gate key whose value
 * is an answer option as stored, such as "never" or "yes"). Aggregates (counts, shares, levels) pass.
 * Every page HR sees runs its props through this (CLAUDE.md, Privacidad).
 *
 * @param  array<array-key, mixed>  $props
 */
function expectNoIndividualResponses(array $props): void
{
    $json = json_encode($props, JSON_THROW_ON_ERROR);

    foreach (App\Modules\Responses\Models\AnonymousResponse::query()->withoutGlobalScopes()->pluck('id') as $id) {
        expect($json)->not->toContain($id);
    }

    expect(individualAnswersIn($props))->toBe([]);
}

/**
 * Where in `$value` one person's answer shows (see expectNoIndividualResponses), as dotted paths.
 *
 * @param  array<array-key, mixed>  $value
 * @return list<string>
 */
function individualAnswersIn(array $value, string $path = 'props'): array
{
    // What a questionnaire stores: the Likert scale of Guías II and III and the yes/no of Guía I and of the gates.
    $options = ['always', 'almost_always', 'sometimes', 'almost_never', 'never', 'yes', 'no'];
    $found = [];

    foreach ($value as $key => $item) {
        $isItem = (ctype_digit((string) $key) && (int) $key > 0) || in_array($key, ['serves_clients', 'supervises'], true);

        if (($key === 'answers' && is_array($item)) || ($isItem && in_array($item, $options, true))) {
            $found[] = $path.'.'.$key;
        } elseif (is_array($item)) {
            $found = [...$found, ...individualAnswersIn($item, $path.'.'.$key)];
        }
    }

    return $found;
}
