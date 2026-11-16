<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
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
use Inertia\Testing\AssertableInertia as Assert;

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
