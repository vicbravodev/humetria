<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\ReissueAccess;
use App\Modules\WorkerAccess\Contracts\WorkerInvitations;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftDeviceKey;

/*
 * No token of a link, nor its hash, nor the email a person writes reaches a log line, the audit trail or the props of a page;
 * and the person's pages show their own name only.
 */
beforeEach(function (): void {
    $this->logPath = storage_path('logs/worker-access-privacy-'.getmypid().'.log');
    File::delete($this->logPath);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath, 'logging.channels.single.level' => 'debug']);
    Log::forgetChannel('single');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    $this->rh = ownerOf($this->tenant);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz', 'email' => 'beto@acme.mx']);
});

afterEach(function (): void {
    File::delete($this->logPath);
});

it('keeps the token, its hash and the email out of the log, the audit trail and the props', function (): void {
    Context::add('user_id', $this->rh->id);
    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);
    $url = '';
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notice, array $channels, object $notifiable) use (&$url): bool {
        $url = (string) $notice->toMail($notifiable)->actionUrl;

        return true;
    });
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    $hash = hash('sha256', $token);

    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'beto@acme.mx'])->assertOk();
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'nadie@acme.mx'])->assertOk();
    $page = $this->get(route('worker.link.show', ['acme', $token]))->assertOk();
    $this->post(route('worker.link.open', ['acme', $token]))->assertRedirect(route('worker.key.show', 'acme'));
    $key = $this->get(route('worker.key.show', 'acme'))->assertOk();
    $this->post(route('worker.link.open', ['acme', $token]));

    $log = (string) File::get($this->logPath);
    $props = json_encode([$page->viewData('page')['props'], $key->viewData('page')['props']], JSON_THROW_ON_ERROR);
    $audit = auditContent(AuditEntry::query()->get());

    expect($log)->toContain('worker_access.link_issued')->toContain('worker_access.link_used')
        ->not->toContain($token)->not->toContain($hash)
        ->not->toContain('beto@acme.mx')->not->toContain('nadie@acme.mx')->not->toContain('ana@acme.mx')
        ->and($audit)->toContain('worker_access.link_issued')->not->toContain($token)->not->toContain($hash)->not->toContain('ana@acme.mx')
        ->and($props)->not->toContain($token)->not->toContain($hash)->not->toContain('ana@acme.mx')
        ->and(json_encode(WorkerAccessLink::query()->get()->toArray(), JSON_THROW_ON_ERROR))->not->toContain($hash);
});

it('shows a person their own name only, never another person\'s', function (): void {
    $key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone']);
    enteredAs($this->ana->id, EntryVia::Key, $key->id);

    $pages = [
        $this->get(route('worker.home', 'acme')),
        $this->get(route('worker.key.show', 'acme')),
        $this->get(URL::temporarySignedRoute('worker.not-me.show', now()->addDay(), ['tenant' => 'acme', 'credential' => $key->id], absolute: false)),
    ];

    foreach ($pages as $page) {
        $page->assertOk()->assertInertia(fn (Assert $inertia) => $inertia->where('company', 'Acme')->etc())
            ->assertDontSee('Beto Ruiz')->assertDontSee('beto@acme.mx')->assertDontSee('ana@acme.mx');
    }
});

it('keeps the device key, the pass, its HMAC, the signature and the challenge out of the log, the audit trail and the props', function (): void {
    $key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    enteredAs($this->ana->id, EntryVia::Key, $key->id);
    $this->withCredentials()->withCookie(config()->string('session.cookie'), Str::random(40));
    $phone = new SoftDeviceKey;

    $challenge = $this->postJson(route('worker.device-key.options', 'acme'))->json('challenge');
    $signature = $phone->sign(Base64UrlSafe::decodeNoPadding($challenge));
    $registered = $this->postJson(route('worker.device-key.store', 'acme'), ['public_key' => $phone->publicKey(), 'challenge' => $challenge, 'signature' => $signature, 'label' => 'Este teléfono'])->assertCreated();
    $renewed = $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => $registered->json('deviceKeyId')])->assertOk();
    $home = $this->get(route('worker.home', 'acme'))->assertOk();
    $keyPage = $this->get(route('worker.key.show', 'acme'))->assertOk();
    Context::add('user_id', $this->rh->id);
    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    $passes = [$registered->json('pass'), $renewed->json('pass')];
    $secrets = [
        $phone->publicKey(), $challenge, $signature, ...$passes,
        ...array_map(fn (string $pass): string => explode('.', $pass)[1], $passes),
        ...array_map(fn (string $pass): string => explode('.', $pass)[2], $passes),
    ];
    $log = (string) File::get($this->logPath);
    $props = json_encode([$home->viewData('page')['props'], $keyPage->viewData('page')['props']], JSON_THROW_ON_ERROR);
    $audit = auditContent(AuditEntry::query()->get());

    expect($log)->toContain('worker_access.device_key_registered')->toContain('worker_access.access_reissued')->toContain('"device_keys_revoked":1')
        ->and($audit)->toContain('worker_access.device_key_registered');
    foreach ($secrets as $secret) {
        expect($log)->not->toContain($secret)
            ->and($audit)->not->toContain($secret)
            ->and($props)->not->toContain($secret);
    }
});
