<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(LeaveDefaults::class);

it('starts a company on vacations today, counting Monday to Friday', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    workIn($tenant = Tenant::factory()->create());

    $setting = resolve(LeaveDefaults::class)->ensure();

    expect($setting->tenant_id)->toBe($tenant->id)
        ->and($setting->tracking_since->toDateString())->toBe('2026-11-10')
        ->and($setting->refresh()->statutory_work_week)->toBe(WorkWeek::MonFri);
});

it('takes today from the local day, not from UTC', function (): void {
    // 05:30 UTC of the 15th is still the 14th in Mexico City.
    $this->travelTo('2026-03-15 05:30:00');
    workIn(Tenant::factory()->create());

    expect(resolve(LeaveDefaults::class)->ensure()->tracking_since->toDateString())->toBe('2026-03-14');
});

it('keeps one row and its start date when asked again', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    workIn(Tenant::factory()->create());
    $first = resolve(LeaveDefaults::class)->ensure();

    $this->travelTo('2026-12-01 18:00:00');
    $second = resolve(LeaveDefaults::class)->ensure();

    expect($second->id)->toBe($first->id)
        ->and($second->tracking_since->toDateString())->toBe('2026-11-10')
        ->and(LeaveSetting::query()->count())->toBe(1);
});

it('logs the start once, with no more than the date', function (): void {
    $this->travelTo('2026-11-10 18:00:00');
    workIn(Tenant::factory()->create());
    Log::spy();

    resolve(LeaveDefaults::class)->ensure();
    resolve(LeaveDefaults::class)->ensure();

    Log::shouldHaveReceived('info')->once()->with('leave.tracking_started', ['tracking_since' => '2026-11-10']);
});

it('does not read the settings of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ofB = LeaveSetting::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(LeaveSetting::query()->count())->toBe(0)
        ->and(LeaveSetting::query()->find($ofB->id))->toBeNull();

    workIn($b);
    expect(LeaveSetting::query()->count())->toBe(1);
});

it('gives each company its own row', function (): void {
    workIn($a = Tenant::factory()->create());
    resolve(LeaveDefaults::class)->ensure();
    workIn($b = Tenant::factory()->create());
    resolve(LeaveDefaults::class)->ensure();

    expect(LeaveSetting::query()->withoutGlobalScopes()->whereIn('tenant_id', [$a->id, $b->id])->count())->toBe(2);
});

it('rejects in the database a second row for a company', function (): void {
    $tenant = Tenant::factory()->create();
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => LeaveSetting::factory()->create(['tenant_id' => $tenant->id]))->toThrow(QueryException::class);
});

it('rejects in the database a work week it does not know', function (): void {
    $row = ['id' => Str::uuid()->toString(), 'tenant_id' => Tenant::factory()->create()->id, 'tracking_since' => '2026-01-01'];

    expect(DB::table('leave_settings')->insert([...$row, 'statutory_work_week' => 'mon_sat']))->toBeTrue()
        ->and(fn () => DB::table('leave_settings')->where('id', $row['id'])->update(['statutory_work_week' => 'sun_sat']))->toThrow(QueryException::class);
});
