<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\VAPID;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\On;
use Pest\Browser\Api\PendingAwaitablePage;

/*
 * The person's own pages, with no account: the screen to enter, a one-time link and their start.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
});

/**
 * A script that says how a screen of state sits (finding 4): its group centered between the top bar and the action (a
 * little above the middle, never far from it), with an 80 px icon and its text centered.
 */
function stateCentered(): string
{
    return <<<'JS'
        () => {
            const group = document.querySelector('[data-slot="state-message"]');
            const top = document.querySelector('header').getBoundingClientRect().bottom;
            const actions = document.querySelector('.sticky.bottom-0');
            const bottom = actions === null ? innerHeight : actions.getBoundingClientRect().top;
            const box = group.getBoundingClientRect();
            const above = box.top - top;
            const below = bottom - box.bottom;
            const icon = group.querySelector('[aria-hidden]').getBoundingClientRect();

            return { balanced: above > 24 && below >= above && below - above < (bottom - top) / 3, icon: icon.width, align: getComputedStyle(group).textAlign };
        }
        JS;
}

/** A one-time link of Ana that still opens, of the kind given. */
function anaLink(string $token, bool $requested = false): void
{
    $factory = WorkerAccessLink::factory()->withToken($token);
    ($requested ? $factory->requested() : $factory)->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id]);
}

it('opens the screen to enter, naming nobody, with the key and the link by email', function (bool $dark, bool $mobile): void {
    accessible(visitPage('worker-access/enter', '/me/acme', $dark, $mobile)
        ->assertSee('Entre a su portal')
        ->assertSee('Entrar con mi huella')
        ->assertSee('Entrar con un enlace por correo')
        ->assertDontSee('Ana López'), 1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('answers the same to an email asked for, whoever it is', function (): void {
    visitPage('worker-access/enter', '/me/acme')
        ->type('input[type=email]', 'nadie@acme.mx')
        ->press('Enviarme un enlace')
        ->assertSee('Si ese correo es de una persona de la empresa');
});

it('opens a link with «Continuar», and its start names the person', function (bool $dark, bool $mobile): void {
    anaLink('browser-token', requested: true);

    $page = accessible(visitPage('worker-access/link', '/me/acme/link/browser-token', $dark, $mobile)
        ->assertSee('Su enlace está listo')
        ->assertScript(stateCentered(), ['balanced' => true, 'icon' => 80, 'align' => 'center']), 1);

    $page->press('Continuar')->waitForText('Todavía sin marcas')->assertSee('Cuenta');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('welcomes a person with the link RH issued by the three steps that follow, naming nobody', function (bool $dark, bool $mobile): void {
    anaLink('first-token');

    $page = accessible(visitPage('worker-access/link', '/me/acme/link/first-token', $dark, $mobile)
        ->assertSee('Le damos la bienvenida')
        ->assertSee('Cree su llave')
        ->assertSee('Póngala en su inicio')
        ->assertSee('Marque su primera entrada')
        ->assertSee('Su huella nunca sale del teléfono')
        ->assertDontSee('Su enlace está listo')
        ->assertDontSee('Ana')
        ->assertScript(spilledOut(), ''), 1);

    $page->press('Continuar')->waitForText('cree su llave de acceso');
})->with([
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('shows the start with the session open, its three tabs, and «Salir» in Cuenta leaves no name behind', function (bool $dark, bool $mobile): void {
    anaLink('home-token', requested: true);

    $page = accessible(visitPage('worker-access/home', '/me/acme/link/home-token', $dark, $mobile, fn ($page) => $page->press('Continuar')->waitForText('Todavía sin marcas'))
        ->assertSeeIn('nav[aria-label="Secciones del portal"]', 'Inicio')
        ->assertSeeIn('nav[aria-label="Secciones del portal"]', 'Pendientes')
        ->assertDontSee('Salir')
        ->assertScript(spilledOut(), ''), 1);

    accessible($page->press('Pendientes')->waitForText('Está al día')->assertScript(settled(), true), 1);
    accessible($page->press('Cuenta')->waitForText('Su cuenta')->assertSee('Ana López')->assertScript(settled(), true), 1);
    $page->press('Salir')->waitForText('Entre a su portal')->assertDontSee('Ana López');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('has Marcar as a tab where the company has attendance, and it takes the person to mark', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
    anaLink('marcar-token', requested: true);

    $page = visitPage('worker-access/home', '/me/acme/link/marcar-token', mobile: true, reach: fn ($page) => $page->press('Continuar')->waitForText('Todavía sin marcas'))
        ->assertSeeIn('nav[aria-label="Secciones del portal"]', 'Marcar')
        ->assertScript('() => document.querySelectorAll(\'nav[aria-label="Secciones del portal"] > div\').length', 4);

    $page->click('nav[aria-label="Secciones del portal"] a')->assertPathIs('/me/acme/punch');
});

it('opens the start on the moment of the person\'s day, with what they did and the step that follows', function (array $marks, string $title, string $step, bool $dark, bool $mobile): void {
    // Midday in Mexico City: the marks, minutes before now, never fall on the day before.
    $this->travelTo(CarbonImmutable::parse('2026-11-25 18:00:00', 'UTC'));
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

    foreach ($marks as $index => $type) {
        AttendanceMarkFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->ana->work_center_id, 'type' => $type, 'marked_at' => now()->subMinutes((count($marks) - $index) * 20)]);
    }
    anaLink('moment-token', requested: true);

    visitPage('worker-access/home', '/me/acme/link/moment-token', $dark, $mobile, fn ($page) => $page->press('Continuar')->waitForText($title))
        ->assertSee($step)
        ->assertSeeIn('[aria-label="Su jornada"]', $title);
})->with([
    'not marked' => [[], 'Listo para su entrada', 'Marcar entrada'],
    'working' => [[MarkType::Start], 'Está trabajando', 'Salir a comer'],
    'on a break' => [[MarkType::Start, MarkType::BreakStart], 'Está en su comida', 'Regresar de comer'],
    'back' => [[MarkType::Start, MarkType::BreakStart, MarkType::BreakEnd], 'Está trabajando', 'Marcar salida'],
    'done' => [[MarkType::Start, MarkType::End], 'Jornada terminada', 'Ver mi semana'],
])->with([
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('offers «Mis marcas» in Cuenta where the company has attendance, and it leads to the weeks; nowhere else', function (bool $attendance): void {
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, [$attendance ? 'attendance' : 'vacations'], CarbonImmutable::now(config('app.display_timezone')));
    anaLink('history-token', requested: true);

    $page = visitPage('worker-access/home', '/me/acme/link/history-token', mobile: true, reach: fn ($page) => $page->press('Continuar')->waitForText('Pendientes'))
        ->click('Cuenta');

    if (! $attendance) {
        $page->assertDontSee('Mis marcas');

        return;
    }

    $page->assertSee('Mis marcas')->click('Mis marcas')->assertPathIs('/me/acme/week');
})->with(['with attendance' => [true], 'without attendance' => [false]]);

it('greets by the hour of the day in a company without attendance too, by first name', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 14:00:00', 'UTC'));
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
    anaLink('greeting-token', requested: true);

    visitPage('worker-access/home', '/me/acme/link/greeting-token', mobile: true, reach: fn ($page) => $page->press('Continuar')->waitForText('Pendientes'))
        ->assertScript('() => /^Buen(os días|as tardes|as noches), Ana$/.test(document.querySelector("h1").textContent)', true);
});

it('shows one screen for a link that does not work', function (): void {
    accessible(visitPage('worker-access/link', '/me/acme/link/unknown', mobile: true)
        ->assertSee('Este enlace ya no sirve')
        ->assertScript(stateCentered(), ['balanced' => true, 'icon' => 80, 'align' => 'center'])
        ->assertSee('Pedir un enlace nuevo'), 1);
});

it('opens RH\'s link straight into creating a key, with the warning on shared accounts', function (bool $dark, bool $mobile): void {
    anaLink('issued-token');

    accessible(visitPage('worker-access/key', '/me/acme/link/issued-token', $dark, $mobile, fn ($page) => $page->press('Continuar')->waitForText('cree su llave de acceso'))
        ->assertSee('Ana López, cree su llave de acceso')
        ->assertSee('Si comparte su cuenta de iCloud o de Google con su familia')
        ->assertSee('Crear llave'), 1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('asks before «No fui yo» revokes a key, then says it is done', function (bool $dark, bool $mobile): void {
    $key = App\Modules\WorkerAccess\Models\WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone']);
    $url = Illuminate\Support\Facades\URL::temporarySignedRoute('worker.not-me.show', now()->addDays(30), ['tenant' => 'acme', 'credential' => $key->id], absolute: false);

    $page = accessible(visitPage('worker-access/not-me', $url, $dark, $mobile)
        ->assertSee('¿No creó usted la llave «iPhone»?')
        ->assertScript(stateCentered(), ['balanced' => true, 'icon' => 80, 'align' => 'center']), 1);

    $page->press('Anular esa llave')->waitForText('La llave quedó anulada')
        ->assertScript(stateCentered(), ['balanced' => true, 'icon' => 80, 'align' => 'center']);
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

/** Ana's start, opened from her link on the phone the device preset says, and with the session open. */
function anaStartOn(On|PendingAwaitablePage $pending): AwaitableWebpage
{
    anaLink('install-token', requested: true);

    $page = $pending->assertNoJavaScriptErrors();
    $page->press('Continuar')->waitForText('Todavía sin marcas');

    return $page;
}

// The device presets set only the screen: the agent says which phone it is.
const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';
const GMAIL_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) GSA/321.0.636 Mobile/15E148 Safari/604.1';
const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36';

it('shows an iPhone the steps to add the app to its home screen, as the last thing in the start', function (bool $dark): void {
    $pending = visit('/me/acme/link/install-token')->on()->iPhone14Pro()->withUserAgent(SAFARI_IPHONE);

    accessible(anaStartOn($dark ? $pending->inDarkMode() : $pending->inLightMode())
        ->assertSee('Instale la app en su teléfono')
        ->assertSee('Toque Compartir')
        ->assertSee('Agregar a pantalla de inicio')
        ->assertSee('El ícono abre Marcar.')
        ->assertDontSee('Ábralo en Safari o Chrome')
        ->assertScript(spilledOut(), '')
        ->assertScript(settled(), true), 1);
})->with([
    'light' => [false],
    'dark' => [true],
]);

it('shows an Android phone how to install the app', function (): void {
    accessible(anaStartOn(visit('/me/acme/link/install-token')->on()->pixel8()->withUserAgent(CHROME_ANDROID)->inLightMode())
        ->assertSee('Instale la app en su teléfono')
        ->assertSee('El ícono abre Marcar.')
        ->assertDontSee('Agregar a pantalla de inicio')
        ->assertScript(settled(), true), 1);
});

it('asks to open the link in Safari or Chrome from the browser inside another app, instead of the install steps', function (): void {
    accessible(anaStartOn(visit('/me/acme/link/install-token')->on()->iPhone14Pro()->withUserAgent(GMAIL_IPHONE)->inLightMode())
        ->assertSee('Ábralo en Safari o Chrome')
        ->assertSee('Copiar el enlace')
        ->assertDontSee('Agregar a pantalla de inicio')
        ->assertScript(spilledOut(), '')
        ->assertScript(settled(), true), 1);
});

it('does not show the install guide on a computer', function (): void {
    anaStartOn(visit('/me/acme/link/install-token')->inLightMode())
        ->assertDontSee('Instale la app en su teléfono')
        ->assertDontSee('Ábralo en Safari o Chrome');
});

/**
 * Makes the page behave as a phone that can push: the system's permission is `default` (or the one given), its box answers «granted»
 * and the worker's registration is a double that keeps what is subscribed in `window` (a real push manager does not exist in the
 * test). `$marked` says whether a mark was accepted in this browser, as use-punch writes it. The start reads all this when it opens,
 * so the test leaves it and comes back (Cuenta, then Inicio).
 */
function pushPhone(AwaitableWebpage $page, bool $marked = true, string $permission = 'default', ?string $subscribedTo = null): void
{
    $marked = $marked ? 'true' : 'false';
    $subscribed = $subscribedTo === null ? 'null' : json_encode($subscribedTo, JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        () => {
            if ({$marked}) localStorage.setItem('humetria-push-marked', '1');
            Object.defineProperty(Notification, 'permission', { get: () => '{$permission}', configurable: true });
            Notification.requestPermission = () => Promise.resolve('granted');
            const make = (endpoint) => ({
                endpoint,
                options: { applicationServerKey: null },
                toJSON: () => ({ endpoint, keys: { p256dh: 'x'.repeat(87), auth: 'y'.repeat(22) } }),
                unsubscribe: () => { window.unsubscribed = true; return Promise.resolve(true); },
            });
            // How many requests that change the notices (POST or DELETE of .../push) have finished: the test waits on it, then looks at the server.
            const real = window.fetch;
            window.pushSent = 0;
            window.fetch = (url, init) => real(url, init).finally(() => {
                if ((init?.method ?? 'GET') !== 'GET' && String(url).endsWith('/push')) window.pushSent++;
            });
            window.subscribed = {$subscribed} === null ? null : make({$subscribed});
            navigator.serviceWorker.getRegistration = () => Promise.resolve({
                pushManager: {
                    getSubscription: () => Promise.resolve(window.subscribed),
                    subscribe: () => { window.subscribed = make('https://fcm.googleapis.com/fcm/send/ana-phone'); return Promise.resolve(window.subscribed); },
                },
            });
        }
        JS);
}

/** Leaves the start and comes back, so it reads the phone again. */
function reopenStart(AwaitableWebpage $page): AwaitableWebpage
{
    return $page->press('Cuenta')->waitForText('Su cuenta')->press('Inicio')->waitForText('Todavía sin marcas');
}

/** Waits until the page's request to the notices is over (the server then has what it will keep), and says how many phones Ana has. */
function anaPhones(AwaitableWebpage $page): int
{
    $page->assertScript(eventually('() => window.pushSent > 0'), true);

    return PushSubscription::query()->where('employee_id', test()->ana->id)->count();
}

beforeEach(function (): void {
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@acme.mx']]);
});

it('does not offer the notices before a first mark was accepted', function (): void {
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());
    pushPhone($page, marked: false);

    reopenStart($page)->assertDontSee('¿Le avisamos?')->assertDontSee('Sí, avisarme');
});

it('offers the notices after a first mark, saying what they will say before the system asks', function (bool $dark, bool $mobile): void {
    $pending = $mobile ? visit('/me/acme/link/install-token')->on()->mobile() : visit('/me/acme/link/install-token');
    $page = anaStartOn($dark ? $pending->inDarkMode() : $pending->inLightMode());
    pushPhone($page);

    accessible(reopenStart($page)
        ->assertSee('¿Le avisamos?')
        ->assertSee('¿Le avisamos cuando Recursos Humanos revise sus marcas sin internet o responda sus correcciones?')
        ->assertSee('Tiene un aviso en Humetría')
        ->assertSee('Sí, avisarme')
        ->assertSee('Ahora no')
        ->assertScript(spilledOut(), '')
        ->assertScript(settled(), true), 1);
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('hides the offer with «Ahora no» and does not bring it back for 14 days', function (): void {
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());
    pushPhone($page);
    reopenStart($page)->assertSee('¿Le avisamos?')->press('Ahora no')->assertDontSee('¿Le avisamos?');

    $page->assertScript("() => localStorage.getItem('humetria-push-dismissed') !== null", true);
    reopenStart($page)->assertDontSee('¿Le avisamos?');
    expect(PushSubscription::query()->count())->toBe(0);
});

it('subscribes the phone when the person says yes, for the person of the session', function (): void {
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());
    pushPhone($page);

    // The comma makes the text look like a list of selectors, so the button is named by a selector.
    reopenStart($page)->press('button:has-text("Sí, avisarme")')->assertScript(eventually('() => !document.body.innerText.includes("¿Le avisamos?")'), true);

    expect(anaPhones($page))->toBe(1)
        ->and(PushSubscription::query()->sole()->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/ana-phone');
});

it('subscribes by itself, with no offer, when the system already allowed the notices', function (): void {
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());
    pushPhone($page, marked: false, permission: 'granted');

    reopenStart($page)->assertDontSee('¿Le avisamos?');

    expect(anaPhones($page))->toBe(1);
});

it('gives the phone up when the person taps «Salir»: the server forgets it and the browser drops it', function (): void {
    PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/ana-phone']);
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());
    pushPhone($page, subscribedTo: 'https://fcm.googleapis.com/fcm/send/ana-phone');

    $page->press('Cuenta')->waitForText('Su cuenta')->press('Salir')->waitForText('Entre a su portal');

    $page->assertScript('() => window.unsubscribed === true', true);
    expect(PushSubscription::query()->count())->toBe(0);
});

it('leaves even when the phone has no push at all', function (): void {
    $page = anaStartOn(visit('/me/acme/link/install-token')->inLightMode());

    $page->press('Cuenta')->waitForText('Su cuenta')->press('Salir')->waitForText('Entre a su portal')->assertDontSee('Ana López');
});
