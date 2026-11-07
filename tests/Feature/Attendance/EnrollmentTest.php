<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\EnrollDevice;
use App\Modules\Attendance\Actions\IssueEnrollment;
use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SoftAuthenticator;

mutates(EnrollDevice::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->token = enrollToken($this->ana->id);
    $this->phone = new SoftAuthenticator;
});

/** A new enrollment link of `$employeeId`, as RH issues it. */
function enrollToken(string $employeeId): string
{
    return basename(resolve(IssueEnrollment::class)->handle($employeeId, (string) User::factory()->create()->id));
}

/** The enrollment route `$name` of the link `$token`. */
function enrollRoute(string $name, string $token, string $slug = 'acme'): string
{
    return route("attendance.device.enroll.{$name}", [$slug, $token]);
}

/**
 * The phone asks for the options, creates its key and sends it with the agreement accepted, as the page does.
 *
 * @param  array<string, mixed>  $overrides
 */
function enrollWith(SoftAuthenticator $phone, string $token, array $overrides = [], bool $userVerified = true): TestResponse
{
    $options = test()->postJson(enrollRoute('options', $token))->assertOk()->json();

    return test()->postJson(enrollRoute('register', $token), [
        'agreement_accepted' => true,
        'agreement_version' => 1,
        'device_label' => 'iPhone',
        'credential' => $phone->create($options, userVerified: $userVerified),
        ...$overrides,
    ]);
}

it('shows the agreement to read and accept, with the person\'s name, and nothing else of them', function (): void {
    $this->get(enrollRoute('show', $this->token))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/enroll')
            ->where('company', 'Acme')
            ->where('name', 'Ana López')
            ->where('agreement.version', 1)
            ->where('agreement.body', AttendanceAgreement::query()->sole()->body)
            ->where('optionsUrl', enrollRoute('options', $this->token))
            ->where('registerUrl', enrollRoute('register', $this->token))
            ->missing('email')
            ->missing('employeeId'));
});

it('gives the options of a new key of this phone, for the person of the link, never cached', function (): void {
    $response = $this->postJson(enrollRoute('options', $this->token))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('user.name'))->toBe('Ana López')
        ->and($response->json('authenticatorSelection.userVerification'))->toBe('required')
        ->and($response->json('challenge'))->toBeString();
});

it('links the phone: it saves the device and the acceptance of the agreement, uses up the link and logs ids only', function (): void {
    Log::spy();

    enrollWith($this->phone, $this->token)->assertOk()->assertExactJson(['enrolled' => true]);

    $device = AttendanceDevice::query()->sole();
    $acceptance = AgreementAcceptance::query()->sole();
    expect($device)->employee_id->toBe($this->ana->id)->label->toBe('iPhone')->sign_count->toBe(0)->revoked_at->toBeNull()
        ->and($device->credential_id)->toBe(rtrim(strtr(base64_encode($this->phone->credentialId), '+/', '-_'), '='))
        ->and($device->enrolled_at->toIso8601String())->toBe('2026-11-07T15:00:00+00:00')
        ->and($acceptance)->device_id->toBe($device->id)->employee_id->toBe($this->ana->id)
        ->and($acceptance->agreement_id)->toBe(AttendanceAgreement::query()->sole()->id)
        ->and(EnrollmentLink::query()->sole()->used_at)->not->toBeNull();

    $entry = AuditEntry::query()->where('event', 'attendance.device_enrolled')->sole();
    expect($entry)->subject_id->toBe($device->id)->metadata->toBe(['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->with('attendance.device_enrolled', ['device_id' => $device->id, 'employee_id' => $this->ana->id]);
});

it('works once: after linking, the link answers 404 everywhere', function (): void {
    enrollWith($this->phone, $this->token)->assertOk();

    $this->get(enrollRoute('show', $this->token))->assertNotFound();
    $this->postJson(enrollRoute('options', $this->token))->assertNotFound();
    $this->postJson(enrollRoute('register', $this->token), ['agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => 'iPhone', 'credential' => ['id' => 'x']])->assertNotFound();
    expect(AttendanceDevice::query()->count())->toBe(1);
});

it('does not let a link that passed its 48 hours link anything', function (): void {
    $options = $this->postJson(enrollRoute('options', $this->token))->json();
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));

    $this->get(enrollRoute('show', $this->token))->assertNotFound();
    $this->postJson(enrollRoute('register', $this->token), ['agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => 'iPhone', 'credential' => $this->phone->create($options)])->assertNotFound();
    expect(AttendanceDevice::query()->count())->toBe(0);
});

it('answers the same 404 for every link that does not work, whatever the reason', function (Closure $link): void {
    [$slug, $token] = $link($this);
    // The error page's props: what it shows, nothing of why.
    $page = fn (TestResponse $response): array => $response->assertNotFound()->inertiaProps();
    $expected = $page($this->get(enrollRoute('show', 'unknown')));

    expect($page($this->get(enrollRoute('show', $token, $slug))))->toBe($expected);
    $this->postJson(enrollRoute('options', $token, $slug))->assertNotFound();
    $this->postJson(enrollRoute('register', $token, $slug), ['agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => 'iPhone', 'credential' => ['id' => 'x']])->assertNotFound();
    expect(AttendanceDevice::query()->count())->toBe(0);
})->with([
    'unknown token' => [fn (): array => ['acme', 'not-a-token']],
    'replaced by a newer link' => [function ($test): array {
        enrollToken($test->ana->id);

        return ['acme', $test->token];
    }],
    'the person left' => [function ($test): array {
        $test->ana->update(['deactivated_at' => now()]);

        return ['acme', $test->token];
    }],
    'expired' => [function ($test): array {
        EnrollmentLink::query()->update(['expires_at' => now()]);

        return ['acme', $test->token];
    }],
    'used' => [function ($test): array {
        EnrollmentLink::query()->update(['used_at' => now()]);

        return ['acme', $test->token];
    }],
    'another company\'s link under this company' => [function (): array {
        $other = Tenant::factory()->create();
        workIn($other);
        $token = enrollToken(EmployeeFactory::new()->create(['tenant_id' => $other->id])->id);

        return ['acme', $token];
    }],
    'this link under another company' => [fn ($test): array => [Tenant::factory()->create()->slug, $test->token]],
    'a suspended company' => [function ($test): array {
        $test->tenant->update(['deactivated_at' => now()]);

        return ['acme', $test->token];
    }],
    'an unknown company' => [fn ($test): array => ['nobody', $test->token]],
]);

it('does not link anything without the agreement accepted', function (array $overrides): void {
    enrollWith($this->phone, $this->token, $overrides)->assertUnprocessable()->assertJsonValidationErrors('agreement_accepted');

    expect(AttendanceDevice::query()->count())->toBe(0)
        ->and(AgreementAcceptance::query()->count())->toBe(0)
        ->and(EnrollmentLink::findPending($this->token))->not->toBeNull();
})->with([
    'not accepted' => [['agreement_accepted' => false]],
    'missing' => [['agreement_accepted' => null]],
]);

it('refuses a key that does not verify with one plain message, and keeps the link for another try', function (): void {
    enrollWith($this->phone, $this->token, userVerified: false)->assertUnprocessable()->assertExactJson(['message' => 'No se pudo crear la llave. Intente de nuevo.']);

    expect(AttendanceDevice::query()->count())->toBe(0)
        ->and(EnrollmentLink::findPending($this->token))->not->toBeNull();
    enrollWith($this->phone, $this->token)->assertOk();
});

it('does not link a phone with a version of the agreement the person did not read', function (): void {
    $this->get(enrollRoute('show', $this->token))->assertInertia(fn (AssertableInertia $page) => $page->where('agreement.version', 1));
    AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 36]);

    enrollWith($this->phone, $this->token)->assertConflict()->assertExactJson(['message' => 'El acuerdo cambió. Recargue la página para leer la versión nueva.']);

    expect(AttendanceDevice::query()->count())->toBe(0)
        ->and(AgreementAcceptance::query()->count())->toBe(0)
        ->and(EnrollmentLink::findPending($this->token))->not->toBeNull();
    enrollWith($this->phone, $this->token, ['agreement_version' => 2])->assertOk();
});

it('does not link a phone for a person who left after the page opened', function (): void {
    $options = $this->postJson(enrollRoute('options', $this->token))->json();
    $this->ana->update(['deactivated_at' => now()]);

    $this->postJson(enrollRoute('register', $this->token), ['agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => 'iPhone', 'credential' => $this->phone->create($options)])->assertNotFound();
    expect(AttendanceDevice::query()->count())->toBe(0);
});

it('asks for a name of the device and a credential', function (): void {
    $this->postJson(enrollRoute('register', $this->token), ['agreement_accepted' => true, 'agreement_version' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['device_label', 'credential']);
    $this->postJson(enrollRoute('register', $this->token), ['agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => str_repeat('a', 61), 'credential' => ['id' => 'x']])
        ->assertUnprocessable()->assertJsonValidationErrors(['device_label']);
});

it('throttles the enrollment routes by address and by link, with their own limiter', function (): void {
    foreach (['show', 'options', 'register'] as $name) {
        expect(Route::getRoutes()->getByName("attendance.device.enroll.{$name}")?->gatherMiddleware())->toContain('throttle:attendance-enroll')
            ->not->toContain('throttle:attendance-device');
    }

    $request = Request::create(enrollRoute('show', $this->token));
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    $limits = RateLimiter::limiter('attendance-enroll')?->__invoke($request);

    expect($limits)->toHaveCount(2)
        ->and($limits[0])->maxAttempts->toBe(30)->decaySeconds->toBe(60)->key->toBe('attendance-enroll-ip:'.$request->ip())
        ->and($limits[1])->maxAttempts->toBe(10)->decaySeconds->toBe(60)->key->toBe('attendance-enroll-link:'.hash('sha256', $this->token));
});

it('stops a link after 10 requests in a minute', function (): void {
    foreach (range(1, 10) as $ignored) {
        $this->get(enrollRoute('show', $this->token))->assertOk();
    }

    $this->get(enrollRoute('show', $this->token))->assertTooManyRequests();
    $this->get(enrollRoute('show', enrollToken(EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id)))->assertOk();
});

it('never writes the key of a device to a log line or the audit trail, from linking it to revoking it', function (): void {
    // The log as it is written, after the redaction, to a file of this test.
    $path = storage_path('logs/enrollment-'.Str::random(12).'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');

    enrollWith($this->phone, $this->token)->assertOk();
    $device = AttendanceDevice::query()->sole();
    $ceremony = resolve(WebAuthnCeremony::class);
    $ceremony->verifyAssertion('s', $this->phone->get($ceremony->assertionOptions('s'), $this->ana->id));
    resolve(RevokeDevice::class)->handle($device->id);
    // Even an event that carried them by mistake.
    Log::info('attendance.probe', ['credential_id' => $device->credential_id, 'public_key' => $device->public_key]);

    $log = File::get($path);
    File::delete($path);
    $written = $log.AuditEntry::query()->get()->toJson();
    expect($log)->toContain('attendance.device_enrolled')->toContain('attendance.device_revoked')->toContain('attendance.probe')
        ->and(AuditEntry::query()->pluck('event')->all())->toContain('attendance.device_enrolled', 'attendance.device_revoked')
        ->and($written)->not->toContain($device->credential_id)->not->toContain($device->public_key)->not->toContain($this->token);
});
