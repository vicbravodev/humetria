<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\DisownKey;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\KeyDisowned;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DisownKey::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone']);
    Notification::fake();
});

function notMeUrl(string $credentialId, string $route = 'worker.not-me.show', string $slug = 'acme'): string
{
    return URL::temporarySignedRoute($route, now()->addDays(30), ['tenant' => $slug, 'credential' => $credentialId], absolute: false);
}

it('asks first: opening the address changes nothing', function (): void {
    $url = notMeUrl($this->key->id);

    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('worker-access/not-me')
        ->where('company', 'Acme')->where('label', 'iPhone')->where('confirmUrl', fn (string $confirm): bool => str_contains($confirm, 'signature='))->etc());

    expect($this->key->refresh()->revoked_at)->toBeNull();
});

it('revokes the key, ends the person\'s sessions, revokes their links, tells the company and keeps it in the audit trail', function (): void {
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    $link = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->travel(1)->seconds();
    $url = notMeUrl($this->key->id);
    Log::spy();

    $this->post($url)->assertRedirect();

    expect($this->key->refresh())->revoked_reason->toBe('not_me')->revoked_at->not->toBeNull()
        ->and($link->refresh()->revoked_at)->not->toBeNull()
        ->and(resolve(WorkerSessionStore::class)->current())->toBeNull()
        ->and(AuditEntry::query()->where('event', 'worker_access.not_me_reported')->sole())->subject_id->toBe($this->key->id)->metadata->toBe(['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->with('worker_access.not_me', ['key_id' => $this->key->id, 'employee_id' => $this->ana->id]);
    Notification::assertSentOnDemand(KeyDisowned::class, function (KeyDisowned $notice, array $channels, object $notifiable): bool {
        $mail = $notice->toMail($notifiable);

        expect($notifiable->routes['mail'])->toBe($this->owner->email)
            ->and($mail->subject)->toBe('Ana López anuló una llave de acceso que no era suya')
            ->and(implode(' ', $mail->introLines))->toContain('«iPhone»');

        return true;
    });
    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('confirmUrl', null)->etc());
});

it('does it once: confirming again writes nothing more', function (): void {
    $url = notMeUrl($this->key->id);

    $this->post($url);
    $this->post($url);

    expect(AuditEntry::query()->where('event', 'worker_access.not_me_reported')->count())->toBe(1);
    Notification::assertCount(1);
});

it('still ends the sessions when RH had revoked the key before, keeping when it was revoked', function (): void {
    $this->key->update(['revoked_at' => now()->subDay(), 'revoked_reason' => 'revoked_by_hr']);
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    $this->travel(1)->seconds();

    $this->post(notMeUrl($this->key->id));

    expect($this->key->refresh())->revoked_reason->toBe('not_me')
        ->and($this->key->revoked_at->toIso8601String())->toBe('2026-11-15T15:00:00+00:00')
        ->and(resolve(WorkerSessionStore::class)->current())->toBeNull();
});

it('answers 403 to an address without a signature, with one altered, or expired', function (string $case): void {
    $url = match ($case) {
        'unsigned' => route('worker.not-me.show', ['acme', $this->key->id], absolute: false),
        'altered' => str_replace($this->key->id, WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id])->id, notMeUrl($this->key->id)),
        'expired' => notMeUrl($this->key->id),
    };

    if ($case === 'expired') {
        $this->travel(31)->days();
    }

    $this->get($url)->assertForbidden();
    $this->post($url)->assertForbidden();
    expect($this->key->refresh()->revoked_at)->toBeNull();
})->with(['unsigned', 'altered', 'expired']);

it('answers 404 to a key of another company, even with a valid signature', function (): void {
    $other = Tenant::factory()->create(['slug' => 'other']);
    $beto = EmployeeFactory::new()->create(['tenant_id' => $other->id]);
    $betoKey = WorkerCredential::factory()->create(['tenant_id' => $other->id, 'employee_id' => $beto->id]);
    workIn($this->tenant);

    $this->get(notMeUrl($betoKey->id))->assertNotFound();
    $this->post(notMeUrl($betoKey->id))->assertNotFound();

    expect($betoKey->refresh()->revoked_at)->toBeNull();
});
