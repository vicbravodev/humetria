<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\ActivateKioskDevice;
use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Attendance\Actions\ReissueKioskDevice;
use App\Modules\Attendance\Actions\RevokeKioskDevice;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(IssueKioskDevice::class, ActivateKioskDevice::class, RevokeKioskDevice::class, ReissueKioskDevice::class, KioskDevice::class);

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
        ->and($kiosk)->work_center_id->toBe($this->center->id)->tenant_id->toBe($this->tenant->id)->revoked_at->toBeNull()->last_seen_at->toBeNull()->activated_at->toBeNull()
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

it('activates a screen once, swapping the link\'s token for a secret only its cookie keeps, logging ids only', function (): void {
    $link = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $kiosk = KioskDevice::query()->sole();
    Log::spy();

    $secret = resolve(ActivateKioskDevice::class)->handle($link);

    expect($secret)->toBeString()->not->toBe($link)
        ->and(strlen((string) $secret))->toBeGreaterThanOrEqual(40)
        ->and($kiosk->fresh())->token_hash->toBe(hash('sha256', (string) $secret))->activated_at->not->toBeNull()
        ->and(resolve(ActivateKioskDevice::class)->handle($link))->toBeNull()
        ->and(resolve(ActivateKioskDevice::class)->handle((string) $secret))->toBeNull();
    Log::shouldHaveReceived('info')->once()->with('attendance.kiosk_activated', ['kiosk_id' => $kiosk->id]);
});

it('does not activate the link of a revoked screen or of an archived center, nor an unknown one', function (): void {
    $revoked = resolve(IssueKioskDevice::class)->handle($this->center->id);
    resolve(RevokeKioskDevice::class)->handle(KioskDevice::query()->sole()->id);
    $archived = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $this->center->update(['archived_at' => now()]);

    expect(resolve(ActivateKioskDevice::class)->handle($revoked))->toBeNull()
        ->and(resolve(ActivateKioskDevice::class)->handle($archived))->toBeNull()
        ->and(resolve(ActivateKioskDevice::class)->handle(''))->toBeNull()
        ->and(KioskDevice::query()->whereNotNull('activated_at')->count())->toBe(0);
});

it('finds an activated screen by its secret while it is not revoked, never by its link', function (): void {
    $link = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $kiosk = KioskDevice::query()->sole();

    expect(KioskDevice::findActive($link))->toBeNull();

    $secret = (string) resolve(ActivateKioskDevice::class)->handle($link);

    expect(KioskDevice::findActive($secret)?->id)->toBe($kiosk->id)
        ->and(KioskDevice::findActive($secret.'x'))->toBeNull()
        ->and(KioskDevice::findActive(''))->toBeNull();

    resolve(RevokeKioskDevice::class)->handle($kiosk->id);

    expect(KioskDevice::findActive($secret))->toBeNull();
});

it('does not find or activate a screen of another company', function (): void {
    $link = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $other = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $token = (string) resolve(ActivateKioskDevice::class)->handle($link);

    workIn(Tenant::factory()->create());

    expect(KioskDevice::findActive($token))->toBeNull()
        ->and(resolve(ActivateKioskDevice::class)->handle($other))->toBeNull()
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

it('writes issuing and revoking a screen to the audit trail with ids only, never its token', function (): void {
    $token = resolve(IssueKioskDevice::class)->handle($this->center->id);
    $kiosk = KioskDevice::query()->sole();
    resolve(RevokeKioskDevice::class)->handle($kiosk->id);
    resolve(RevokeKioskDevice::class)->handle($kiosk->id);

    $entries = AuditEntry::query()->whereIn('event', ['attendance.kiosk_issued', 'attendance.kiosk_revoked'])->orderBy('created_at')->orderBy('event')->get();
    expect($entries->pluck('event')->all())->toBe(['attendance.kiosk_issued', 'attendance.kiosk_revoked'])
        ->and($entries->pluck('subject_id')->unique()->all())->toBe([$kiosk->id])
        ->and($entries->pluck('metadata')->all())->toBe([['work_center_id' => $this->center->id], ['work_center_id' => $this->center->id]])
        ->and($entries->toJson())->not->toContain($token)->not->toContain($kiosk->token_hash);
});

describe('issuing a screen\'s link again', function (): void {
    it('gives a new link, killing the one before, and the cookie of whoever opened it', function (): void {
        $first = resolve(IssueKioskDevice::class)->handle($this->center->id);
        $kiosk = KioskDevice::query()->sole();
        // A chat's link preview opened it before the tablet did.
        $bot = (string) resolve(ActivateKioskDevice::class)->handle($first);
        Log::spy();

        $again = resolve(ReissueKioskDevice::class)->handle($kiosk->id);
        Log::shouldHaveReceived('info')->once()->with('attendance.kiosk_reissued', ['kiosk_id' => $kiosk->id]);

        expect($again)->toBeString()->not->toBe($first)
            ->and($kiosk->fresh())->activated_at->toBeNull()->token_hash->toBe(hash('sha256', (string) $again))
            ->and(KioskDevice::findActive($bot))->toBeNull()
            ->and(resolve(ActivateKioskDevice::class)->handle($first))->toBeNull()
            ->and(resolve(ActivateKioskDevice::class)->handle((string) $again))->toBeString()
            ->and(KioskDevice::query()->count())->toBe(1);
        expect(AuditEntry::query()->where('event', 'attendance.kiosk_reissued')->sole())->subject_id->toBe($kiosk->id)->metadata->toBe(['work_center_id' => $this->center->id]);
    });

    it('refuses a screen already showing the code, or revoked', function (): void {
        $seen = KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'activated_at' => now(), 'last_seen_at' => now()]);
        $revoked = KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'revoked_at' => now()]);

        expect(resolve(ReissueKioskDevice::class)->handle($seen->id))->toBeNull()
            ->and(resolve(ReissueKioskDevice::class)->handle($revoked->id))->toBeNull()
            ->and($seen->fresh()?->token_hash)->toBe($seen->token_hash)
            ->and(AuditEntry::query()->where('event', 'attendance.kiosk_reissued')->count())->toBe(0);
    });

    it('does not touch a screen of another company', function (): void {
        $other = KioskDevice::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        expect(fn () => resolve(ReissueKioskDevice::class)->handle($other->id))->toThrow(ModelNotFoundException::class);
    });
});
