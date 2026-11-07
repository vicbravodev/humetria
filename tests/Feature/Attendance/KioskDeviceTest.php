<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Attendance\Actions\RevokeKioskDevice;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(IssueKioskDevice::class, RevokeKioskDevice::class, KioskDevice::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenterFactory::new()->createOne(['tenant_id' => $this->tenant->id]);
});

it('issues a screen for a work center, giving its token once and keeping only its hash, logging ids only', function (): void {
    Log::spy();

    $token = resolve(IssueKioskDevice::class)->handle($this->center->id);

    $kiosk = KioskDevice::query()->sole();
    $row = json_encode(DB::table('attendance_kiosk_devices')->first(), JSON_THROW_ON_ERROR);
    expect(strlen($token))->toBeGreaterThanOrEqual(40)
        ->and($kiosk)->work_center_id->toBe($this->center->id)->tenant_id->toBe($this->tenant->id)->revoked_at->toBeNull()->last_seen_at->toBeNull()
        ->and($kiosk->token_hash)->toBe(hash('sha256', $token))
        ->and($row)->not->toContain($token)
        ->and(resolve(IssueKioskDevice::class)->handle($this->center->id))->not->toBe($token);
    Log::shouldHaveReceived('info')->with('attendance.kiosk_issued', ['kiosk_id' => $kiosk->id, 'work_center_id' => $this->center->id]);
});

it('does not issue a screen for an archived or foreign work center', function (): void {
    $archived = WorkCenterFactory::new()->archived()->createOne(['tenant_id' => $this->tenant->id]);
    $foreign = WorkCenterFactory::new()->createOne(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => resolve(IssueKioskDevice::class)->handle($archived->id))->toThrow(WorkCenterNotAvailable::class)
        ->and(fn () => resolve(IssueKioskDevice::class)->handle($foreign->id))->toThrow(WorkCenterNotAvailable::class)
        ->and(KioskDevice::query()->count())->toBe(0);
});

it('finds the screen of a token while it is not revoked', function (): void {
    $token = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $kiosk = KioskDevice::query()->sole();

    expect(KioskDevice::findActive($token)?->id)->toBe($kiosk->id)
        ->and(KioskDevice::findActive($token.'x'))->toBeNull()
        ->and(KioskDevice::findActive(''))->toBeNull();

    resolve(RevokeKioskDevice::class)->handle($kiosk->id);

    expect(KioskDevice::findActive($token))->toBeNull();
});

it('does not find a screen of another company', function (): void {
    $token = resolve(IssueKioskDevice::class)->handle($this->center->id);

    workIn(Tenant::factory()->create());

    expect(KioskDevice::findActive($token))->toBeNull()
        ->and(KioskDevice::query()->count())->toBe(0);
});

it('revokes a screen once, keeping when it was revoked, logging ids only', function (): void {
    resolve(IssueKioskDevice::class)->handle($this->center->id);
    $kiosk = KioskDevice::query()->sole();
    Log::spy();

    $this->travelTo(now()->startOfMinute());
    resolve(RevokeKioskDevice::class)->handle($kiosk->id);
    $revokedAt = $kiosk->fresh()?->revoked_at;
    $this->travel(5)->minutes();
    resolve(RevokeKioskDevice::class)->handle($kiosk->id);

    expect($revokedAt?->toIso8601String())->toBe(now()->subMinutes(5)->toIso8601String())
        ->and($kiosk->fresh()?->revoked_at?->toIso8601String())->toBe($revokedAt?->toIso8601String());
    Log::shouldHaveReceived('info')->once()->with('attendance.kiosk_revoked', ['kiosk_id' => $kiosk->id]);
});

it('does not revoke a screen of another company', function (): void {
    $other = KioskDevice::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => resolve(RevokeKioskDevice::class)->handle($other->id))->toThrow(ModelNotFoundException::class)
        ->and($other->fresh()?->revoked_at)->toBeNull();
});
