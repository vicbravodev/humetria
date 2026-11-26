<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Sso\Contracts\DomainOwnership;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request as ToolRequest;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\On;
use Pest\Browser\Api\PendingAwaitablePage;
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
    ->in('Feature', 'Browser', 'Ssr');

// Preparing the demo publishes the NOM-037 documents as PDFs, and any module may store its files: every module's disk is
// fake, so no test leaves files on a real one.
pest()->beforeEach(function (): void {
    foreach (config()->array('billing.storage_disks') as $key) {
        Storage::fake(config()->string("filesystems.{$key}"));
    }
})->in('Feature/Demo');

// Each CI runner drives four browsers at once: an action that waits for a button or a page gets 15 s instead of Pest's 5 s,
// which a busy runner ran out of now and then (a click, a calendar day). It waits longer, it asserts nothing less.
pest()->browser()->timeout(15_000);

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

/**
 * Runs one named group of a test whose groups share one costly state (the demo's stories, on one prepared company). A
 * failure stays a failure and leads with the group's name and the line of the group that failed.
 */
function section(string $name, Closure $group): void
{
    try {
        $group();
    } catch (PHPUnit\Framework\ExpectationFailedException $failure) {
        throw new PHPUnit\Framework\ExpectationFailedException(sectionLabel($name, $failure).$failure->getMessage(), $failure->getComparisonFailure(), $failure);
    } catch (PHPUnit\Framework\AssertionFailedError $failure) {
        throw new PHPUnit\Framework\AssertionFailedError(sectionLabel($name, $failure).$failure->getMessage(), 0, $failure);
    } catch (Throwable $error) {
        throw new RuntimeException(sectionLabel($name, $error).$error::class.": {$error->getMessage()}", 0, $error);
    }
}

/** «[name] (tests/…/file.php:line) »: the first line under tests/ the throwable passed through, Pest.php aside. */
function sectionLabel(string $name, Throwable $error): string
{
    foreach ([['file' => $error->getFile(), 'line' => $error->getLine()], ...$error->getTrace()] as $frame) {
        $file = $frame['file'] ?? '';

        if (str_contains($file, '/tests/') && ! str_ends_with($file, '/Pest.php')) {
            return "[{$name}] (".substr($file, strpos($file, '/tests/') + 1).':'.($frame['line'] ?? 0).') ';
        }
    }

    return "[{$name}] ";
}

/**
 * What an audit entry says, as text, for a privacy assertion: the event, the subject and the metadata. Not its UUIDs,
 * its actor or its dates: they hold digit runs («50000») that a short number looked for in the whole entry meets by chance.
 *
 * @param  AuditEntry|iterable<AuditEntry>  $entries
 */
function auditContent(AuditEntry|iterable $entries): string
{
    $entries = $entries instanceof AuditEntry ? [$entries] : $entries;
    $content = [];

    foreach ($entries as $entry) {
        $content[] = Arr::except($entry->toArray(), ['id', 'tenant_id', 'actor_id', 'request_id', 'created_at']);
    }

    return json_encode($content, JSON_THROW_ON_ERROR);
}

/**
 * Makes an existing company the demo, for a test that builds the company first (through factories of its rows). The
 * database refuses it outside tests: whether a company is the demo is fixed when it is created (migration 2027_01_21), as
 * Tenant::factory()->demo() and the demo's story do.
 */
function becomeDemo(Tenant|string $tenant): void
{
    DB::statement('ALTER TABLE tenants DISABLE TRIGGER tenants_is_demo_is_fixed');
    DB::table('tenants')->where('id', $tenant instanceof Tenant ? $tenant->id : $tenant)->update(['is_demo' => true]);
    DB::statement('ALTER TABLE tenants ENABLE TRIGGER tenants_is_demo_is_fixed');

    if ($tenant instanceof Tenant) {
        $tenant->refresh();
    }
}

function superadmin(): User
{
    $user = User::factory()->create();
    // The platform role belongs to no company: assigned outside any team.
    setPermissionsTeamId(null);
    $user->assignRole(User::SUPERADMIN);

    return $user;
}

function support(): User
{
    $user = User::factory()->create();
    // The platform role belongs to no company: assigned outside any team.
    setPermissionsTeamId(null);
    $user->assignRole(User::SUPPORT);

    return $user;
}

/**
 * The columns that suspend a company, for tests that write them straight to the table.
 *
 * @return array<string, mixed>
 */
function suspension(): array
{
    return ['restriction' => 'suspended', 'restriction_reason' => 'Suspendida', 'restricted_at' => now()];
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
/**
 * What deactivating someone asks for: the day they left (today, in the company's day) and why.
 *
 * @param  array<string, mixed>  $with
 * @return array<string, mixed>
 */
function departure(array $with = []): array
{
    return ['terminated_on' => Carbon\CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString(), 'reason' => 'resignation', ...$with];
}

function workIn(Tenant $tenant): void
{
    resolve(TenantContext::class)->set($tenant);
}

/**
 * `$employeeId` accepted their company's current agreement on the registro de jornada (criterio 5.1) with `$credentialId`
 * (a new key of theirs when none): without it, they cannot mark. Works in the company of the person.
 */
function agreementAccepted(string $employeeId, ?string $credentialId = null): void
{
    $person = App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($employeeId);
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $credentialId ??= App\Modules\WorkerAccess\Models\WorkerCredential::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $employeeId])->id;

    resolve(App\Modules\Attendance\Actions\AcceptAgreement::class)->handle($employeeId, $credentialId, resolve(App\Modules\Attendance\Support\CurrentAgreement::class)->get()->version);
}

/**
 * Opens a session of `$employeeId` in their company on this test's browser, as entering does (with their key unless told
 * otherwise; a key needs its id). Works in the company of the person.
 */
function enteredAs(string $employeeId, App\Modules\WorkerAccess\Enums\EntryVia $via = App\Modules\WorkerAccess\Enums\EntryVia::Key, ?string $credentialId = null): void
{
    $person = App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($employeeId);
    workIn(Tenant::query()->findOrFail($person->tenant_id));

    resolve(App\Modules\WorkerAccess\Support\WorkerSessionStore::class)->start($employeeId, $via, $credentialId);
}

/**
 * Enters as `$employeeId` (enteredAs) and gives the slug of their company, for the person's pages: `route('portal.…', asPerson($id))`.
 */
function asPerson(string $employeeId): string
{
    enteredAs($employeeId);

    return Tenant::query()->findOrFail(App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($employeeId)->tenant_id)->slug;
}

/** The telework portal of an employee of the current company, as their session opens it (Telework\Support\SessionPortal). */
function teleworkPortalOf(string $employeeId): App\Modules\Telework\Support\OpenedPortal
{
    $person = App\Modules\Organization\Models\Employee::query()->findOrFail($employeeId);
    $company = resolve(App\Modules\Tenancy\Contracts\Companies::class)->current();

    return resolve(App\Modules\Telework\Support\SessionPortal::class)->for(new App\Modules\WorkerAccess\Data\WorkerIdentity($company->id, $person->id, $company->tradeName, $person->name));
}

/**
 * In the browser: opens `$path` in the page and waits for it to load, once. Not navigate(): Pest retries it like an assertion,
 * one second per attempt, so a page that takes longer (a busy CI runner) is asked for again while it loads and the next read
 * lands in the new load («Execution context was destroyed»). A pending page opens on its own address first.
 */
function openPath(AwaitableWebpage|PendingAwaitablePage|On $page, string $path): AwaitableWebpage
{
    $webpage = $page instanceof AwaitableWebpage ? $page : $page->assertNoJavaScriptErrors();
    $webpage->page()->goto(Pest\Browser\Support\ComputeUrl::from($path));

    return $webpage;
}

/**
 * In the browser: reloads the page and waits for it to load, once. Not refresh(): Pest retries it like an assertion, one second
 * per attempt, so a reload that takes longer reloads again and the next step lands in that second load («Execution context was
 * destroyed»), as openPath() explains for navigate().
 */
function reloadPage(AwaitableWebpage|PendingAwaitablePage $page): AwaitableWebpage
{
    $webpage = $page instanceof AwaitableWebpage ? $page : $page->assertNoJavaScriptErrors();
    $webpage->page()->reload();

    return $webpage;
}

/**
 * In the browser: no accessibility issues at `$level` or worse (axe; 0 critical … 3 minor), as Pest's assertNoAccessibilityIssues()
 * checks them, but axe gets the timeout of the suite. Pest gives it 5 s whatever that timeout is, and a page whose main thread is
 * busy (the landing's videos on a CI runner without a GPU) ran out of them although axe takes a tenth of a second here.
 */
function accessible(AwaitableWebpage $page, int $level = 1): AwaitableWebpage
{
    $threshold = Pest\Browser\Enums\AccessibilityIssueLevel::tryFromLevel($level) ?? throw new InvalidArgumentException("No accessibility level [{$level}].");
    $browser = $page->page();
    $browser->waitForLoadState('networkidle');
    $browser->waitForFunction('document.readyState === "complete"');
    $found = Pest\Browser\Playwright\Playwright::usingTimeout(
        Pest\Browser\Playwright\Playwright::timeout(),
        fn (): mixed => $browser->evaluate('async () => ((await window.axe.run()).violations)'),
    );
    $violations = array_values(array_filter(is_array($found) ? $found : [], function (mixed $violation) use ($threshold): bool {
        $impact = is_array($violation) && is_string($violation['impact'] ?? null) ? $violation['impact'] : null;

        // As Pest counts them: one without an impact counts at any level.
        return $impact === null || Pest\Browser\Enums\AccessibilityIssueLevel::from($impact)->level() <= $threshold->level();
    }));

    expect($violations)->toBeEmpty(Pest\Browser\Support\AccessibilityFormatter::format($violations));

    return $page;
}

/**
 * In the browser: the steps of a person (`$employeeId`) who opened a page of theirs without a session and was sent to enter.
 * They open a one-time link they asked for (made here), press «Continuar» and land on that page, which shows `$text`.
 * Use as the `$reach` of visitPage() with the person's page as its address, or on a page just opened with visit() (pending:
 * it opens on the first step); go on with the page it returns.
 *
 * @return Closure(AwaitableWebpage|PendingAwaitablePage|On): AwaitableWebpage
 */
function enteringTo(string $employeeId, string $text): Closure
{
    $person = App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($employeeId);
    $slug = Tenant::query()->findOrFail($person->tenant_id)->slug;
    $token = Str::random(40);
    App\Modules\WorkerAccess\Models\WorkerAccessLink::factory()->withToken($token)->requested()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id]);
    // The session cookie lasts until the browser closes: one dated by a test clock set in the past (travelTo) comes already
    // expired to the browser, which runs on the real clock, and every request would start a new session.
    config(['session.expire_on_close' => true]);

    return fn (AwaitableWebpage|PendingAwaitablePage|On $page): AwaitableWebpage => openPath($page, "/me/{$slug}/link/{$token}")->press('Continuar')->waitForText($text);
}

/**
 * In the browser: a person without a key, on the step to prove it is them (the page or the dialog of a delicate form; the
 * mail must be faked), asks for the code, writes the one emailed to them and goes on to what they asked for, which shows
 * `$text`.
 */
function provedWithCode(AwaitableWebpage $page, string $text): mixed
{
    $page->press('Enviarme un código')->waitForText('Le enviamos un código');
    $code = null;
    Illuminate\Support\Facades\Notification::assertSentOnDemand(App\Modules\WorkerAccess\Notifications\ProofCode::class, function (App\Modules\WorkerAccess\Notifications\ProofCode $notice) use (&$code): bool {
        $code = $notice->code;

        return true;
    });

    return $page->type('input[autocomplete="one-time-code"]', $code ?? throw new LogicException('No code was sent.'))->press('Confirmar código')->waitForText($text);
}

/**
 * In the browser: enteringTo(), and then, as a person without a key does before something delicate, the screen to prove it is
 * them with a code by email (the mail is faked if it was not), and back on the page, which shows `$text`. Its delicate forms
 * then go straight through: the proof holds while the test's clock stands still.
 *
 * @return Closure(AwaitableWebpage|PendingAwaitablePage|On): AwaitableWebpage
 */
function enteringProvedTo(string $employeeId, string $text): Closure
{
    $enter = enteringTo($employeeId, $text);
    $slug = Tenant::query()->findOrFail(App\Modules\Organization\Models\Employee::query()->withoutGlobalScopes()->findOrFail($employeeId)->tenant_id)->slug;

    return function (AwaitableWebpage|PendingAwaitablePage|On $pending) use ($enter, $slug, $text): AwaitableWebpage {
        if (! Illuminate\Support\Facades\Notification::getFacadeRoot() instanceof Illuminate\Support\Testing\Fakes\NotificationFake) {
            Illuminate\Support\Facades\Notification::fake();
        }

        $page = $enter($pending);
        $there = $page->url();
        provedWithCode(openPath($page, "/me/{$slug}/proof")->waitForText('Confirme que es usted'), 'Pendientes');

        return openPath($page, $there)->waitForText($text);
    };
}

/**
 * The scope Humi works in for a new person of `$tenant` with `$role`, as the server builds it for a request there.
 */
function assistantScopeIn(Tenant $tenant, TenantRole $role = TenantRole::Hr): AssistantScope
{
    $user = memberOf($tenant, $role);
    workIn($tenant);

    return resolve(AssistantScopeFactory::class)->make($user);
}

/**
 * Runs a tool of Humi for `$scope` as the model would and decodes the card it answers.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function toolCard(string $tool, AssistantScope $scope, array $arguments = []): array
{
    $card = json_decode((string) resolve($tool, ['scope' => $scope])->handle(new ToolRequest($arguments)), true, flags: JSON_THROW_ON_ERROR);

    return is_array($card) ? $card : [];
}

/** A conversation with one message that the participant owns. */
function assistantConversation(ConversationParticipant $participant, string $title = 'Avance', ?CarbonInterface $updatedAt = null): Conversation
{
    $conversation = Conversation::query()->create([
        'id' => (string) Str::uuid(),
        'participant_type' => 'assistant_participant',
        'participant_id' => $participant->id,
        'title' => $title,
        ...($updatedAt === null ? [] : ['created_at' => $updatedAt, 'updated_at' => $updatedAt]),
    ]);
    $conversation->messages()->create([
        'id' => (string) Str::uuid(),
        'participant_type' => 'assistant_participant',
        'participant_id' => $participant->id,
        'agent' => 'HumiAssistant',
        'role' => 'user',
        'content' => 'Hola',
        'attachments' => '[]',
        'steps' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'status' => 'completed',
    ]);

    return $conversation;
}

/** An answer of Humi in a conversation of the participant, as the SDK stores it; returns its id. */
function assistantAnswer(ConversationParticipant $participant, string $conversationId, string $content = 'Va bien.'): string
{
    $message = ConversationMessage::query()->create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'participant_type' => 'assistant_participant',
        'participant_id' => $participant->id,
        'agent' => 'HumiAssistant',
        'role' => 'assistant',
        'content' => $content,
        'attachments' => '[]',
        'steps' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'status' => 'completed',
    ]);

    return $message->id;
}

/** The company of a row of another module, which knows it only by its id. */
function tenantOf(string $tenantId): Tenant
{
    return Tenant::query()->findOrFail($tenantId);
}

/** A new user who belongs to `$tenant` with `$role`. */
function memberOf(Tenant $tenant, TenantRole $role): User
{
    // A fixed name: a random one («Rosario») contains the first name that a privacy assertion looks for in the same page.
    $user = User::factory()->create(['name' => 'Miembro '.$role->value]);
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

    return pageIsHealthy($page, $mobile);
}

/**
 * What every screen has to be once it is open, Inertia or not (the shell of the installed app is not): no English left,
 * no JavaScript errors, no CSP violations, a settled page, touch targets a finger can hit (on a phone) and no
 * accessibility issues (axe, level 1). `visitPage` and the shell's `visitShell` both end here.
 */
function pageIsHealthy(AwaitableWebpage $page, bool $mobile): AwaitableWebpage
{
    $page->assertScript(englishWords(), '[]');

    $page->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        // axe reads colors as painted: let fade-ins finish so it judges the settled page, not a frame mid-transition
        // (endless ones, like the OTP caret blink, never finish and are left alone). An animation the page cancels while we wait (the
        // kiosk restarts its countdown) rejects `finished` with an AbortError: that one is over too, so it is not a failure.
        ->assertScript('() => Promise.all(document.getAnimations().filter((animation) => animation.effect?.getTiming().iterations !== Infinity).map((animation) => animation.finished.catch(() => undefined))).then(() => true)')
        // An enter animation can start after that check (an IntersectionObserver fires a frame later, or a slow runner
        // reaches it late), so the page counts as settled only after ten frames in a row with nothing finite running. A page that
        // restarts a finite animation on purpose (the kiosk countdown) never goes still, so the wait gives up after 800 ms.
        ->assertScript(<<<'JS'
            () => new Promise((resolve) => {
                let still = 0;
                const start = performance.now();
                const frame = () => {
                    const moving = document.getAnimations().some((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations !== Infinity);
                    still = moving ? 0 : still + 1;
                    still >= 10 || performance.now() - start > 800 ? resolve(true) : requestAnimationFrame(frame);
                };
                requestAnimationFrame(frame);
            })
            JS);

    if ($mobile) {
        $page->assertScript(smallTouchTargets(), '');
    }

    return accessible($page, 1);
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
 * The source files under a directory of resources/js. The lint probes of FrontendArchitectureTest write and delete
 * `zz-probe-*` files and folders there while other tests run in parallel: they are left out, so a walk never trips on
 * a folder that vanished halfway.
 *
 * @return list<SplFileInfo>
 */
function frontendSourceFiles(string $directory = ''): array
{
    $root = dirname(__DIR__).'/resources/js'.($directory === '' ? '' : '/'.$directory);
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), 'zz-probe-'),
    );
    $files = [];

    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file instanceof SplFileInfo) {
            $files[] = $file;
        }
    }

    return $files;
}

/**
 * A script that resolves once the page's path matches, the way assertPathIs reads it (`*` is a wildcard). A click that
 * navigates with Inertia changes the URL a moment later, and assertPathIs reads it at once: on a busy runner it read the
 * old page. Run this before assertPathIs; it waits up to 10 s and the assertion still decides.
 */
function pathBecomes(string $path): string
{
    $pattern = json_encode('^'.str_replace('\\*', '.*', preg_quote($path, '/')).'$', JSON_THROW_ON_ERROR);

    return <<<JS
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                const done = new RegExp({$pattern}).test(location.pathname);
                done || performance.now() - start > 10000 ? resolve(true) : requestAnimationFrame(check);
            };
            check();
        })
        JS;
}

/**
 * A script that polls a condition (the source of an arrow function) until it holds, up to 10 s, and resolves with its
 * last answer. Use it as `->assertScript(eventually('() => …'), true)`: the assertion waits for what it asserts, where a
 * `->wait(1)` before it passes or fails by the speed of the runner. A condition that throws (the node is not there yet) is false.
 */
function eventually(string $condition): string
{
    $source = json_encode($condition, JSON_THROW_ON_ERROR);

    return <<<JS
        () => new Promise((resolve) => {
            const condition = new Function('return (' + {$source} + ')')();
            const start = performance.now();
            const check = () => {
                let holds = false;
                try { holds = Boolean(condition()); } catch (error) { holds = false; }
                holds || performance.now() - start > 10000 ? resolve(holds) : requestAnimationFrame(check);
            };
            check();
        })
        JS;
}

/**
 * A script (the source of an arrow function) that runs once in the page however many times Pest sends it. Pest gives every
 * attempt of `script()` and `assertScript()` 1 s and sends it again until it answers, while the first one keeps running:
 * a script that clicks or types for longer would do it several times at once. Every attempt waits on the same run.
 */
function runOnce(string $script): string
{
    $run = json_encode('pest-run-'.bin2hex(random_bytes(8)), JSON_THROW_ON_ERROR);

    return "() => (window[{$run}] ??= ({$script})())";
}

/** A script that resolves true once nothing finite is animating or in transition (a toast fading in, a menu closing). */
function settled(): string
{
    return eventually('() => document.getAnimations().every((animation) => animation.playState !== "running" || animation.effect?.getComputedTiming().iterations === Infinity)');
}

/**
 * A script that resolves once no calendar is left in the page, in a popover (desktop) or in a sheet (phone): picking a day
 * closes it with a short animation and, when it unmounts, gives focus back to its field. A click that opens the next date
 * field before that would see its calendar closed again by that focus, so this waits for the unmount, not just for the
 * closed state. A sheet that holds no calendar (a form's own) is not waited on. A combobox's list is a popover too and
 * gives focus back the same way: wait for it after picking an option and before opening a date field.
 */
function calendarClosed(): string
{
    return <<<'JS'
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                const open = document.querySelector('[data-slot="popover-content"], [data-slot="sheet-content"]:has([data-slot="calendar"])') !== null;
                ! open || performance.now() - start > 3000 ? resolve(! open) : requestAnimationFrame(check);
            };
            check();
        })
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
 * A complete Guía II or III whose every item gets the answer that scores 0 points (Tablas 2 and 5): a questionnaire that
 * lands in Nulo in every category and domain.
 *
 * @return array<string, string>
 */
function nom035HealthyAnswers(string $key): array
{
    $instruments = resolve(Instruments::class);
    $definition = $instruments->definition($key);
    $options = array_column($definition->options, 'value');
    $answers = [];

    foreach ($definition->sections as $section) {
        foreach ($section->items as $item) {
            $answers[(string) $item->number] = array_find($options, fn (string $option): bool => $instruments->itemScore($key, $item->number, $option) === 0) ?? $options[0];
        }
    }

    $answers['serves_clients'] = 'yes';
    $answers['supervises'] = 'yes';

    return $answers;
}

/**
 * A closed evaluation of `$tenant` whose Guía II (or III) `$guide` was answered by `$count` anonymous questionnaires, all
 * with `$answers`, in `$area` (a new one by default).
 *
 * @param  array<string, string>  $answers
 */
function closedEvaluationWith(Tenant $tenant, array $answers, int $count = 5, string $guide = 'nom035.guide-ii', ?App\Modules\Organization\Models\Area $area = null): App\Modules\Campaigns\Models\Campaign
{
    $campaign = App\Modules\Campaigns\Models\Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'instruments' => ['nom035.guide-i', $guide], 'workers' => $guide === 'nom035.guide-iii' ? 60 : 30]);
    $area ??= App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id]);
    App\Modules\Responses\Models\AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => $guide, 'answers' => $answers]);

    return $campaign;
}

/**
 * A closed evaluation of `$tenant` whose Guía II was answered `$value` by as many anonymous questionnaires as each area
 * of `$sizes` says (area name => questionnaires).
 *
 * @param  array<string, int>  $sizes
 * @return array{App\Modules\Campaigns\Models\Campaign, array<string, App\Modules\Organization\Models\Area>}
 */
function closedEvaluationInAreas(Tenant $tenant, array $sizes, string $value = 'always'): array
{
    $campaign = App\Modules\Campaigns\Models\Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-ii'], 'workers' => 30]);
    $areas = [];

    foreach ($sizes as $name => $count) {
        $areas[$name] = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
        App\Modules\Responses\Models\AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $areas[$name]->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', $value)]);
    }

    return [$campaign, $areas];
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

/** A question of a checklist version, for tests of the NOM-037 checklist. */
function addQuestion(App\Modules\Telework\Models\Checklist $checklist, int $position, string $favorable, bool $sensitive = false, int $section = 1): App\Modules\Telework\Models\ChecklistQuestion
{
    return App\Modules\Telework\Models\ChecklistQuestion::query()->create([
        'tenant_id' => $checklist->tenant_id,
        'checklist_id' => $checklist->id,
        'section' => $section,
        'position' => $position,
        'code' => "S{$section}-{$position}",
        'heading' => 'Espacio',
        'text' => "Pregunta {$position}",
        'origin' => 'humetria',
        'favorable' => $favorable,
        'sensitive' => $sensitive,
    ]);
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

/** @return array<string, string> a complete survey: stands, and every item with the same answer. */
function surveyAnswers(string $answer = 'yes'): array
{
    $answers = ['stands' => 'yes'];

    foreach (resolve(Instruments::class)->definition('seating.survey')->itemNumbers() as $number) {
        $answers[(string) $number] = $answer;
    }

    return $answers;
}

/*
| The inputs of the contract calculator's unit tests (scenarioInput, scenarioCell, scenarioFiscal…), shared by several files.
*/
require_once __DIR__.'/Unit/Compensation/ScenarioHelpers.php';

/*
| The time clock's browser helpers (a phone's fingerprint reader and its position), shared by its browser tests.
*/
require_once __DIR__.'/Browser/attendance-helpers.php';

/*
| The installed app's browser helpers (its shell, its service worker, a camera that shows a QR), shared by its browser tests.
*/
require_once __DIR__.'/Browser/pwa-helpers.php';

/*
| The signed POST to Meta's webhook (postMeta), shared by the webhook, STOP, ALTA and service-reply tests: a file run on its own
| (or in another parallel process) must not depend on the one that used to define it.
*/
require_once __DIR__.'/Feature/Messaging/meta-helpers.php';

/**
 * Issues `$employeeId` the link RH sends to enter and create their key, and gives its path: the one emailed to them (the
 * mail must be faked), or the one RH hands over when they have no email.
 */
function accessLinkPath(string $employeeId, ?int $userId = null): string
{
    $outcome = resolve(App\Modules\WorkerAccess\Contracts\WorkerInvitations::class)->send($employeeId, $userId ?? User::factory()->create()->id);
    $url = $outcome->url;

    if ($url === null) {
        Illuminate\Support\Facades\Notification::assertSentOnDemand(App\Modules\WorkerAccess\Notifications\WorkerInvitation::class, function (App\Modules\WorkerAccess\Notifications\WorkerInvitation $notification, array $channels, Illuminate\Notifications\AnonymousNotifiable $notifiable) use (&$url): bool {
            $url = (string) $notification->toMail($notifiable)->actionUrl;

            return true;
        });
    }

    return is_string($url) ? (string) parse_url($url, PHP_URL_PATH) : throw new LogicException('No access link.');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function subscriptionInput(array $overrides = []): array
{
    return [
        'plan' => 'cumplimiento',
        'profile' => 'direct',
        'seats' => 100,
        'centers' => 2,
        'status' => 'active',
        'starts_on' => '2026-12-01',
        'negotiated_price' => '',
        'negotiated_valid_until' => '',
        ...$overrides,
    ];
}

/**
 * A company paid by a new account of that kind ('direct' or 'consultant').
 *
 * @return array{0: Tenant, 1: BillingAccount}
 */
function paidCompany(string $kind = 'direct'): array
{
    $account = $kind === 'consultant' ? BillingAccount::factory()->consultant()->create() : BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);

    return [$tenant, $account];
}

/**
 * A company with single sign-on switched on (the module the Enterprise plan carries) and its owner.
 *
 * @return array{0: Tenant, 1: User}
 */
function ssoCompany(string $slug = 'acme'): array
{
    $tenant = Tenant::factory()->create(['slug' => $slug]);
    TenantModule::factory()->for($tenant)->create(['module' => 'sso', 'source' => 'grant', 'reason' => 'Enterprise']);

    return [$tenant, memberOf($tenant, TenantRole::Owner)];
}

/** @param array<string, mixed> $overrides */
function ssoForm(array $overrides = []): array
{
    return [
        'issuer' => 'https://idp.acme.test',
        'client_id' => 'humetria-acme',
        'client_secret' => 'super-secret-value',
        'domains' => ['acme.mx', 'acme.com.mx'],
        ...$overrides,
    ];
}

/** Makes the DNS say that these domains published their record. */
function dnsProves(string ...$domains): void
{
    app()->instance(DomainOwnership::class, new class($domains) implements DomainOwnership
    {
        /** @param list<string> $domains */
        public function __construct(private array $domains) {}

        public function proves(string $domain, string $token): bool
        {
            return in_array($domain, $this->domains, true);
        }
    });
}

/**
 * A company with single sign-on switched on and a proven domain (acme.mx), its owner, and an HR member who signs in with it.
 *
 * @return array{0: Tenant, 1: User, 2: User}
 */
function ssoReady(string $slug = 'acme', string $domain = 'acme.mx', string $issuer = 'https://idp.acme.test'): array
{
    [$tenant, $owner] = ssoCompany($slug);
    SsoConnection::factory()->enabled()->create(['tenant_id' => $tenant->id, 'issuer' => $issuer]);
    SsoDomain::factory()->verified()->create(['tenant_id' => $tenant->id, 'domain' => $domain]);
    $member = memberOf($tenant, TenantRole::Hr);
    $member->forceFill(['email' => "rh@{$domain}"])->save();

    return [$tenant, $owner, $member];
}

/**
 * A fake identity provider: its discovery document and its token endpoint, which hands out an ID token with these claims.
 *
 * @param  array<string, mixed>  $claims  over the defaults of a good token.
 * @param  array<string, mixed>  $discovery  over the defaults of its discovery document.
 */
function idpAnswers(array $claims = [], int $tokenStatus = 200, array $discovery = [], string $issuer = 'https://idp.acme.test'): void
{
    $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    $token = $encode(['alg' => 'RS256']).'.'.$encode([
        'iss' => $issuer, 'aud' => 'humetria-acme', 'sub' => 'u-1', 'nonce' => 'n-1', 'exp' => now()->addMinutes(5)->timestamp, 'iat' => now()->timestamp,
        'email' => 'rh@acme.mx', 'email_verified' => true,
        ...$claims,
    ]).'.firma';

    Http::fake([
        "{$issuer}/.well-known/openid-configuration" => Http::response(['issuer' => $issuer, 'authorization_endpoint' => "{$issuer}/authorize", 'token_endpoint' => "{$issuer}/token", ...$discovery]),
        "{$issuer}/token" => $tokenStatus === 200 ? Http::response(['id_token' => $token, 'access_token' => 'a', 'token_type' => 'Bearer']) : Http::response(['error' => 'invalid_grant'], $tokenStatus),
    ]);
}

/** The callback of the identity provider, with the session that the start of the sign-in leaves. */
function ssoCallback(Tenant $tenant, array $query = [], array $session = []): Illuminate\Testing\TestResponse
{
    return test()->withSession(['sso.tenant_id' => $tenant->id, 'sso.nonce' => 'n-1', 'state' => 'st-1', 'code_verifier' => 'v-1', ...$session])
        ->get(route('sso.callback', ['code' => 'c-1', 'state' => 'st-1', ...$query]));
}

/**
 * A company that the account pays, with a plan.
 *
 * @param  array<string, mixed>  $attributes  of the Subscription; defaults to a paying channel Cumplimiento of 100 people and 2 centers
 */
function planFor(BillingAccount $account, array $attributes = [], ?Tenant $tenant = null): Tenant
{
    $tenant ??= Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Cumplimiento, 'profile' => Profile::ChannelCompliance, 'seats' => 100, 'centers' => 2, ...$attributes]);

    return $tenant;
}

/**
 * Puts the company on a plan whose campaign email quota for the month is exactly `$quota` (the floor, with nothing per seat).
 */
function withMailQuota(Tenant $tenant, int $quota): void
{
    config(['billing.versions.v1.plans.cumplimiento.quotas.email_minimum' => $quota, 'billing.versions.v1.plans.cumplimiento.quotas.email_per_seat' => 0]);
    [, $account] = paidCompany();
    planFor($account, [], $tenant);
}

function statementMonth(): Carbon\CarbonImmutable
{
    return Carbon\CarbonImmutable::parse('2026-12-01');
}

/**
 * A direct company with a plan from a month before; 100 people and 2 centers of Cumplimiento unless said otherwise.
 *
 * @param  array<string, mixed>  $plan
 * @return array{0: Tenant, 1: Subscription}
 */
function statementCompany(array $plan = []): array
{
    [$tenant] = paidCompany();
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01', ...$plan]);

    return [$tenant, $subscription];
}

/**
 * Fails when the props of a page hold something one person of a climate or pulse survey wrote or gave: the id of a stored
 * response or comment, the text of a comment, a map named `answers`, or a map from a question key to a number (a
 * question's key may appear as a value, in a list of results, never as a key with its answer).
 *
 * @param  array<array-key, mixed>  $props
 */
function expectNoEngagementResponses(array $props): void
{
    $json = json_encode($props, JSON_THROW_ON_ERROR);

    foreach (App\Modules\Engagement\Models\Response::query()->withoutGlobalScopes()->pluck('id') as $id) {
        expect($json)->not->toContain($id);
    }

    foreach (App\Modules\Engagement\Models\Comment::query()->withoutGlobalScopes()->get(['id', 'body']) as $comment) {
        expect($json)->not->toContain($comment->id)->not->toContain($comment->body);
    }

    expect(engagementAnswersIn($props))->toBe([]);
}

/**
 * Where in `$value` an answer of one person shows, as dotted paths.
 *
 * @param  array<array-key, mixed>  $value
 * @return list<string>
 */
function engagementAnswersIn(array $value, string $path = 'props'): array
{
    $keys = array_map(fn (App\Modules\Engagement\Data\BankItem $item): string => $item->key, App\Modules\Engagement\Support\Bank\ItemBank::all());
    $found = [];

    foreach ($value as $key => $item) {
        if (($key === 'answers' && is_array($item)) || (in_array($key, $keys, true) && is_int($item))) {
            $found[] = "{$path}.{$key}";
        } elseif (is_array($item)) {
            $found = [...$found, ...engagementAnswersIn($item, "{$path}.{$key}")];
        }
    }

    return $found;
}

/**
 * A closed climate survey of `$tenant` with `$count` anonymous responses. Every agreement question is answered 4 and the
 * recommendation 9, unless `$answers` says otherwise for person `$i`; `$segment` is the coarse group they all belong to.
 *
 * @param  (Closure(int): array<string, int|null>)|null  $answers
 * @param  array<string, string|null>  $segment  work_center_id, area_id, tenure_band, manager_group_id
 */
function climateResultsSurvey(Tenant $tenant, int $count, ?Closure $answers = null, array $segment = [], bool $closed = true): App\Modules\Engagement\Models\Survey
{
    $factory = App\Modules\Engagement\Models\Survey::factory();
    $survey = ($closed ? $factory->closed() : $factory->active())->create(['tenant_id' => $tenant->id, 'invited' => max($count, 12)]);
    $everyone = [];
    foreach (App\Modules\Engagement\Support\Bank\ItemBank::all() as $item) {
        if ($item->scale !== App\Modules\Engagement\Enums\ItemScale::Open) {
            $everyone[$item->key] = $item->scale === App\Modules\Engagement\Enums\ItemScale::Recommend ? 9 : 4;
        }
    }

    for ($i = 0; $i < $count; $i++) {
        App\Modules\Engagement\Models\Response::factory()->create([...$segment, 'tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'answers' => $answers === null ? $everyone : $answers($i)]);
    }

    return $survey;
}

/**
 * A closed pulse of the company in `$mode` with `$count` responses; `$answer` is what each gives to every question that
 * is an agreement (the recommendation, 9).
 */
function pulseResultsSurvey(Tenant $tenant, App\Modules\Engagement\Enums\PulseMode $mode, int $count, int $answer = 5, ?string $manager = null): App\Modules\Engagement\Models\Survey
{
    $refs = App\Modules\Engagement\Support\Bank\PulseComposer::compose($mode, [App\Modules\Engagement\Enums\Driver::Clarity], 0, true);
    $survey = App\Modules\Engagement\Models\Survey::factory()->closed()->pulse()->create(['tenant_id' => $tenant->id, 'pulse_mode' => $mode, 'items' => $refs, 'invited' => max($count, 12)]);
    $answers = [];

    foreach ($refs as $ref) {
        $key = explode('@', $ref)[0];
        $answers[$key] = $key === 'eng.recommend' ? 9 : $answer;
    }

    App\Modules\Engagement\Models\Response::factory()->count($count)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $manager, 'answers' => $answers]);

    return $survey;
}

/**
 * A closed climate survey of `$tenant` whose responses belong to the groups of managers in `$groups`: the id of the manager
 * (an employee) => [how many answered, the answer 1 to 5 each gives to the questions about their manager]. Everything else
 * is answered 4 (the recommendation, 9).
 *
 * @param  array<string, array{int, int}>  $groups
 */
function climateManagerSurvey(Tenant $tenant, array $groups): App\Modules\Engagement\Models\Survey
{
    $survey = App\Modules\Engagement\Models\Survey::factory()->closed()->create(['tenant_id' => $tenant->id, 'invited' => 40]);

    foreach ($groups as $manager => [$count, $level]) {
        $answers = [];
        foreach (App\Modules\Engagement\Support\Bank\ItemBank::all() as $item) {
            if ($item->scale !== App\Modules\Engagement\Enums\ItemScale::Open) {
                $manages = $item->block === App\Modules\Engagement\Enums\ItemBlock::Manager;
                $answers[$item->key] = $item->scale === App\Modules\Engagement\Enums\ItemScale::Recommend ? 9 : ($manages ? $level : 4);
            }
        }

        App\Modules\Engagement\Models\Response::factory()->count($count)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $manager, 'answers' => $answers]);
    }

    return $survey;
}

/**
 * Switches the page to the print media, as the browser does for paper or a PDF, without firing `beforeprint`.
 * Pest's browser plugin has no emulateMedia, so this sends Playwright's own message to the page.
 */
function emulatePrint(AwaitableWebpage $page): AwaitableWebpage
{
    $inner = (new ReflectionProperty(AwaitableWebpage::class, 'page'))->getValue($page);
    $guid = (new ReflectionProperty($inner, 'guid'))->getValue($inner);
    iterator_to_array(Pest\Browser\Playwright\Client::instance()->execute($guid, 'emulateMedia', ['media' => 'print']));

    return $page;
}

/**
 * What a dossier carries on paper and not on screen: the letterhead with the Humetría logo, the company and the center,
 * and the date it was issued (the sheet's page number comes from the `dossier` named page).
 */
function assertPrintLetterhead(AwaitableWebpage $page, string $center): void
{
    $page->assertScript('() => getComputedStyle(document.querySelector(\'[data-slot="print-letterhead"]\')).display', 'none');

    emulatePrint($page)
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-slot="print-letterhead"]\')).display', 'flex')
        ->assertScript('() => document.querySelector(\'[data-slot="print-letterhead"]\').firstElementChild.getBoundingClientRect().width > 0', true)
        ->assertScript('() => /Emitido el \\d{1,2} \\w+ \\d{4}/.test(document.querySelector(\'[data-slot="print-letterhead"]\').innerText)', true)
        ->assertScript("() => document.querySelector('[data-slot=\"print-letterhead\"]').innerText.includes(".json_encode($center).')', true)
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-slot="print-letterhead"]\').parentElement).page', 'dossier');
}
