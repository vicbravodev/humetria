<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Data\NewAccountData;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;

mutates(AcceptInvitation::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
});

function acceptFor(Tenant $tenant): void
{
    app(AcceptInvitation::class)->handle(
        TenantInvitation::factory()->for($tenant)->create(),
        new NewAccountData('Ana Pérez', 'secreto-largo-123'),
    );
}

it('starts the trial on the first acceptance of a company that has not started', function (): void {
    $tenant = Tenant::factory()->create();
    Log::spy();

    acceptFor($tenant);

    expect($tenant->fresh()?->trial_started_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00');

    $entry = AuditEntry::query()->where('event', 'tenant.trial_started')->sole();

    expect($entry->tenant_id)->toBe($tenant->id)
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->actor_id)->toBe(TenantMembership::query()->sole()->user_id);

    Log::shouldHaveReceived('info', ['tenant.trial_started', ['tenant_id' => $tenant->id]])->once();
});

it('leaves the dates alone when the company is active, deactivated, in trial or past its trial', function (Tenant $tenant): void {
    $before = $tenant->only(['trial_started_at', 'active_until', 'deactivated_at']);
    Log::spy();

    acceptFor($tenant);

    expect($tenant->fresh()?->only(['trial_started_at', 'active_until', 'deactivated_at']))->toEqual($before)
        ->and(AuditEntry::query()->where('event', 'tenant.trial_started')->exists())->toBeFalse()
        ->and(TenantMembership::query()->count())->toBe(1);

    Log::shouldNotHaveReceived('info', ['tenant.trial_started', Mockery::any()]);
})->with([
    'active' => [fn () => Tenant::factory()->activeUntil(now()->addMonth())->create()],
    'deactivated' => [fn () => Tenant::factory()->inTrial(now()->subDays(3))->deactivated()->create()],
    'in trial' => [fn () => Tenant::factory()->inTrial(now()->subDays(3))->create()],
    'past its trial' => [fn () => Tenant::factory()->inTrial(now()->subDays(20))->create()],
]);

it('refuses an invitation that stopped being pending after it was read', function (string $change): void {
    $invitation = TenantInvitation::factory()->create();
    TenantInvitation::query()->whereKey($invitation->id)->update([$change => now()]);

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, new NewAccountData('Ana Pérez', 'secreto-largo-123')))
        ->toThrow(InvitationNoLongerValid::class);

    expect(User::query()->count())->toBe(0)
        ->and(TenantMembership::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
})->with(['accepted_at', 'cancelled_at']);
