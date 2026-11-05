<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\ActivateChannel;
use App\Modules\Complaints\Actions\PauseChannel;
use App\Modules\Complaints\Actions\ResumeChannel;
use App\Modules\Complaints\Actions\RotateAccessCode;
use App\Modules\Complaints\Actions\SaveChannelSettings;
use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Events\ComplaintChannelCodeRotated;
use App\Modules\Complaints\Events\ComplaintChannelPaused;
use App\Modules\Complaints\Events\ComplaintChannelPublished;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ActivateChannel::class, PauseChannel::class, ResumeChannel::class, RotateAccessCode::class, SaveChannelSettings::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 03:30:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
});

/** @param array<string, mixed> $overrides */
function channelForm(array $overrides = []): array
{
    return ['procedure' => 'Recibimos la queja, acusamos recibo y la atiende una persona designada.', 'acknowledge_within_days' => 3, 'resolve_within_days' => 30, 'retention_months' => 24, ...$overrides];
}

it('shows the channel page to the people who manage it, with what an inactive channel offers', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/channel')
            ->where('channel.status', 'inactive')
            ->where('channel.url', null)
            ->where('settings.acknowledgeWithinDays', 3)
            ->where('settings.resolveWithinDays', 30)
            ->where('settings.retentionMonths', 24)
            ->where('bounds.retentionMonths', ['min' => 12, 'max' => 60])
            ->has('settings.procedure'));
});

it('says who receives each kind of complaint by name, for the whole company, and who could be designated (board V4-Quejas-Canal)', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $committee = memberOf($this->tenant, TenantRole::Committee);
    $plant = App\Modules\Tenancy\Models\WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    App\Modules\Complaints\Models\ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    App\Modules\Complaints\Models\ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $hr->id]);
    App\Modules\Complaints\Models\ComplaintHandler::factory()->forCenter($this->tenant->id, $plant->id)->create(['user_id' => $committee->id]);
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();

    $this->actingAs($this->owner)
        ->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->has('coverage', 6)
            ->where('coverage.1', ['kind' => 'coworker', 'label' => 'Compañera o compañero', 'excludedRole' => null, 'handlers' => [
                ['name' => $this->owner->name, 'roleLabel' => 'Dueño'],
                ['name' => $hr->name, 'roleLabel' => 'RH'],
            ]])
            ->where('coverage.2.kind', 'hr')
            ->where('coverage.2.excludedRole', 'hr')
            ->where('coverage.2.handlers', [['name' => $this->owner->name, 'roleLabel' => 'Dueño']])
            ->where('coverage.3.excludedRole', 'owner')
            ->where('coverage.3.handlers', [['name' => $hr->name, 'roleLabel' => 'RH']])
            ->where('handlersCount', 3)
            ->has('candidates', 3)
            ->where('candidates', fn ($candidates) => collect($candidates)->contains(['value' => (string) $committee->id, 'label' => $committee->name, 'role' => 'committee', 'roleLabel' => 'Comisión de seguridad e higiene']))
            ->where('channel.qr', fn (string $qr): bool => str_starts_with($qr, 'data:image/')));
});

it('gives no QR for a channel that was never activated', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('channel.qr', null)->where('handlersCount', 0)->where('coverage.0.handlers', []));
});

it('activates the channel: a code, today as the publication day, an audit entry, a log line and the event', function (): void {
    Event::fake([ComplaintChannelPublished::class]);
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.complaints.channel.activate', $this->tenant), channelForm())
        ->assertRedirect(route('tenant.complaints.channel.show', $this->tenant));

    $channel = ComplaintChannel::query()->sole();
    expect($channel->access_code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{16}$/')
        ->and($channel->enabled_at?->toDateTimeString())->toBe('2026-11-20 03:30:00')
        ->and($channel->published_on->toDateString())->toBe('2026-11-19')
        ->and($channel->paused_at)->toBeNull()
        ->and($channel->publicUrl())->toBe(url('/c/'.$channel->access_code));
    Event::assertDispatched(ComplaintChannelPublished::class, fn (ComplaintChannelPublished $event): bool => $event->tenantId === $this->tenant->id && $event->publishedOn->toDateString() === '2026-11-19');
    expect(AuditEntry::query()->where('event', 'complaint_channel.activated')->sole()->subject_id)->toBe($channel->id);
    Log::shouldHaveReceived('info')->with('complaints.channel_activated', ['channel_id' => $channel->id])->once();
});

it('does not activate a channel without its written procedure', function (string $procedure): void {
    $this->actingAs($this->owner)
        ->post(route('tenant.complaints.channel.activate', $this->tenant), channelForm(['procedure' => $procedure]))
        ->assertSessionHasErrors('procedure');

    expect(ComplaintChannel::query()->count())->toBe(0);
})->with(['empty' => [''], 'only spaces' => ['    ']]);

it('keeps the publication day when the channel is activated again after a pause', function (): void {
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['published_on' => '2026-01-05']);
    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.pause', $this->tenant))->assertRedirect();
    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.resume', $this->tenant))->assertRedirect();

    expect($channel->refresh()->published_on->toDateString())->toBe('2026-01-05')
        ->and($channel->paused_at)->toBeNull();
});

it('pauses and resumes the channel, with its event, audit entry and log line', function (): void {
    Event::fake([ComplaintChannelPaused::class]);
    Log::spy();
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();

    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.pause', $this->tenant))->assertRedirect();

    expect($channel->refresh()->paused_at?->toDateTimeString())->toBe('2026-11-20 03:30:00');
    Event::assertDispatched(ComplaintChannelPaused::class, fn (ComplaintChannelPaused $event): bool => $event->tenantId === $this->tenant->id);
    expect(AuditEntry::query()->where('event', 'complaint_channel.paused')->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('complaints.channel_paused', ['channel_id' => $channel->id])->once();

    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.resume', $this->tenant))->assertRedirect();

    expect($channel->refresh()->paused_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'complaint_channel.resumed')->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('complaints.channel_resumed', ['channel_id' => $channel->id])->once();
});

it('rotates the access code, which makes the printed one useless', function (): void {
    Event::fake([ComplaintChannelCodeRotated::class]);
    Log::spy();
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['access_code' => 'AAAAAAAAAAAAAAAA']);

    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.rotate', $this->tenant))->assertRedirect();

    expect($channel->refresh()->access_code)->not->toBe('AAAAAAAAAAAAAAAA')->toMatch('/^[0-9A-HJKMNP-TV-Z]{16}$/')
        ->and($channel->access_code_rotated_on?->toDateString())->toBe('2026-11-19');
    Event::assertDispatched(ComplaintChannelCodeRotated::class, fn (ComplaintChannelCodeRotated $event): bool => $event->tenantId === $this->tenant->id);
    expect(AuditEntry::query()->where('event', 'complaint_channel.code_rotated')->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('complaints.code_rotated', ['channel_id' => $channel->id])->once();
});

it('saves the settings of an existing channel without touching its code', function (): void {
    Log::spy();
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $code = $channel->access_code;

    $this->actingAs($this->owner)
        ->put(route('tenant.complaints.channel.update', $this->tenant), channelForm(['procedure' => 'Nuevo procedimiento.', 'acknowledge_within_days' => 5, 'resolve_within_days' => 45, 'retention_months' => 36]))
        ->assertRedirect();

    expect($channel->refresh())->procedure->toBe('Nuevo procedimiento.')
        ->acknowledge_within_days->toBe(5)->resolve_within_days->toBe(45)->retention_months->toBe(36)
        ->access_code->toBe($code);
    expect(AuditEntry::query()->where('event', 'complaint_channel.settings_updated')->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('complaints.settings_updated', ['channel_id' => $channel->id])->once();
});

it('accepts the exact limits of the settings and rejects what is past them', function (string $field, int $value, bool $valid): void {
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $response = $this->actingAs($this->owner)->put(route('tenant.complaints.channel.update', $this->tenant), channelForm([$field => $value]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'acknowledge 0' => ['acknowledge_within_days', 0, false],
    'acknowledge 1' => ['acknowledge_within_days', 1, true],
    'acknowledge 15' => ['acknowledge_within_days', 15, true],
    'acknowledge 16' => ['acknowledge_within_days', 16, false],
    'resolve 6' => ['resolve_within_days', 6, false],
    'resolve 7' => ['resolve_within_days', 7, true],
    'resolve 180' => ['resolve_within_days', 180, true],
    'resolve 181' => ['resolve_within_days', 181, false],
    'retention 11' => ['retention_months', 11, false],
    'retention 12' => ['retention_months', 12, true],
    'retention 60' => ['retention_months', 60, true],
    'retention 61' => ['retention_months', 61, false],
]);

it('lets only the people who manage the channel change it', function (TenantRole $role, bool $allowed): void {
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $person = memberOf($this->tenant, $role);

    foreach ([
        ['get', 'tenant.complaints.channel.show', []],
        ['put', 'tenant.complaints.channel.update', channelForm()],
        ['post', 'tenant.complaints.channel.pause', []],
        ['post', 'tenant.complaints.channel.rotate', []],
    ] as [$method, $route, $data]) {
        $response = $this->actingAs($person)->{$method}(route($route, $this->tenant), $data);
        $allowed ? expect($response->getStatusCode())->not->toBe(403) : $response->assertForbidden();
    }
})->with([
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
    'complaints' => [TenantRole::Complaints, false],
]);

it('keeps one company\'s channel out of another\'s', function (): void {
    $other = Tenant::factory()->create();
    $theirs = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
    workIn($this->tenant);

    expect(ComplaintChannel::query()->find($theirs->id))->toBeNull()
        ->and(ComplaintChannel::query()->count())->toBe(0);

    $this->actingAs($this->owner)
        ->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('channel.status', 'inactive'));
    $this->actingAs($this->owner)->get(route('tenant.complaints.channel.show', $other))->assertNotFound();
});

it('has one channel per company', function (): void {
    ComplaintChannel::factory()->forTenant($this->tenant->id)->create();

    expect(fn () => ComplaintChannel::factory()->forTenant($this->tenant->id)->create())->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('tells the other modules what they may publish about the channel, and nothing more', function (): void {
    workIn($this->tenant);
    $channels = resolve(ComplaintChannels::class);

    $none = $channels->current();
    expect($none->available)->toBeFalse()->and($none->url)->toBeNull()->and($none->publishedOn)->toBeNull();

    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['procedure' => 'Así se atiende.', 'acknowledge_within_days' => 4, 'resolve_within_days' => 40, 'published_on' => '2026-02-01']);
    $data = $channels->current();

    expect($data->available)->toBeTrue()
        ->and($data->url)->toBe($channel->publicUrl())
        ->and($data->publishedOn?->toDateString())->toBe('2026-02-01')
        ->and($data->types)->toBe(['unfavorable_practice', 'workplace_violence', 'traumatic_event_notice'])
        ->and($data->anonymousAllowed)->toBeTrue()
        ->and($data->acknowledgeWithinDays)->toBe(4)
        ->and($data->resolveWithinDays)->toBe(40)
        ->and($data->procedure)->toBe('Así se atiende.');

    $channel->forceFill(['paused_at' => now()])->save();
    $paused = $channels->current();
    expect($paused->available)->toBeFalse()->and($paused->url)->toBeNull();
});
