<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Playwright;

/*
 * WebAuthn needs a domain, not an address: the browser refuses a relying party on 127.0.0.1. These tests open the app on
 * localhost (the same server) and give the page a virtual authenticator through the Chrome DevTools Protocol, as a
 * phone's fingerprint reader: Pest Browser does not expose Playwright's CDP session, so it is opened with Playwright's own
 * protocol message (BrowserContext.newCDPSession) on the page Pest drives.
 */
beforeEach(function (): void {
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** The enrollment link of Ana, as a path. */
function enrollPath(): string
{
    $url = resolve(IssueEnrollment::class)->handle(test()->ana->id, (string) User::factory()->create()->id);

    return (string) parse_url($url, PHP_URL_PATH);
}

/** A private property of Pest Browser's objects: the page it drives and the Playwright ids behind it. */
function pestInternal(object $object, string $property): mixed
{
    return (new ReflectionProperty($object, $property))->getValue($object);
}

/**
 * Makes the page's origin the relying party, and gives the page a platform authenticator with a resident key and
 * user verification that always succeeds (the person touches the reader).
 */
function withFingerprintReader(AwaitableWebpage $page, bool $verifies = true): void
{
    $origin = parse_url($page->url(), PHP_URL_SCHEME).'://'.parse_url($page->url(), PHP_URL_HOST).':'.parse_url($page->url(), PHP_URL_PORT);
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => [$origin]]);

    $playwrightPage = pestInternal($page, 'page');
    $context = $playwrightPage->context();
    $session = null;

    foreach (Client::instance()->execute((string) pestInternal($context, 'guid'), 'newCDPSession', ['page' => ['guid' => pestInternal($playwrightPage, 'guid')]]) as $message) {
        $session = $message['result']['session']['guid'] ?? $session;
    }

    $send = fn (string $method, array $params): array => iterator_to_array(Client::instance()->execute((string) $session, 'send', ['method' => $method, 'params' => $params]));
    $send('WebAuthn.enable', ['enableUI' => false]);
    $send('WebAuthn.addVirtualAuthenticator', ['options' => [
        'protocol' => 'ctap2',
        'transport' => 'internal',
        'hasResidentKey' => true,
        'hasUserVerification' => true,
        'isUserVerified' => $verifies,
        'automaticPresenceSimulation' => true,
    ]]);
}

it('shows the agreement to read, and the button waits for it to be accepted', function (bool $dark, bool $mobile): void {
    visitPage('attendance/enroll', enrollPath(), $dark, $mobile)
        ->assertSee('Vincule su teléfono, Ana López')
        ->assertSee('Acuerdo de registro electrónico')
        ->assertSee('Qué se guarda')
        ->assertSee('solo en el momento en que usted marca')
        ->assertSee('Registro de jornada')
        ->assertButtonDisabled('Aceptar y vincular este teléfono');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('links the phone: the person accepts, the phone creates a verified key, and the server keeps it', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));

    $page->click('Leí el acuerdo y lo acepto.')
        ->press('Aceptar y vincular este teléfono')
        ->waitForText('Su teléfono quedó vinculado')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    $device = AttendanceDevice::query()->sole();
    expect($device)->employee_id->toBe($this->ana->id)->label->toBeIn(['iPhone', 'iPad', 'Android', 'Mac', 'Windows', 'Dispositivo'])
        ->and(AgreementAcceptance::query()->sole()->device_id)->toBe($device->id);
});

it('says the key was not created when the person is not verified, and links nothing', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), reach: fn (AwaitableWebpage $page) => withFingerprintReader($page, verifies: false));

    $page->click('Leí el acuerdo y lo acepto.')
        ->press('Aceptar y vincular este teléfono')
        ->waitForText('No se creó la llave. Vuelva a intentarlo')
        ->assertNoJavaScriptErrors();

    expect(AttendanceDevice::query()->count())->toBe(0);
});
