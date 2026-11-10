<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\MeasureStorage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

mutates(MeasureStorage::class);

const STORAGE_METER = 'storage_bytes';

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    config(['billing.storage_disks' => ['telework_files', 'seating_files'], 'filesystems.telework_files' => 'tw', 'filesystems.seating_files' => 'se']);
    Storage::fake('tw');
    Storage::fake('se');
});

function storageUsed(Tenant $tenant): int
{
    return (int) UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', STORAGE_METER)->sum('used');
}

it('adds up the real size of everything under each type and company prefix, on every module disk', function (): void {
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", str_repeat('a', 1000));
    Storage::disk('tw')->put("notices/{$tenant->id}/n1/deep/b.jpg", str_repeat('b', 500));
    Storage::disk('se')->put("seats/{$tenant->id}/c1/c.jpg", str_repeat('c', 250));

    resolve(MeasureStorage::class)->handle();

    expect(storageUsed($tenant))->toBe(1750);
});

it('keeps each company to its own bytes and ignores what belongs to no company', function (): void {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    Storage::disk('tw')->put("documents/{$a->id}/a.pdf", str_repeat('a', 10));
    Storage::disk('tw')->put("documents/{$b->id}/b.pdf", str_repeat('b', 20));
    Storage::disk('tw')->put('documents/not-a-company/x.pdf', str_repeat('x', 999));
    Storage::disk('tw')->put('loose.txt', str_repeat('x', 999));

    resolve(MeasureStorage::class)->handle();

    expect(storageUsed($a))->toBe(10)->and(storageUsed($b))->toBe(20);
});

it('counts a disk once however many modules point at it', function (): void {
    config(['filesystems.seating_files' => 'tw']);
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", str_repeat('a', 100));

    resolve(MeasureStorage::class)->handle();

    expect(storageUsed($tenant))->toBe(100);
});

it('sets the figure instead of adding to it: measuring twice, or after growth, gives what is there', function (): void {
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", str_repeat('a', 100));

    resolve(MeasureStorage::class)->handle();
    resolve(MeasureStorage::class)->handle();
    expect(storageUsed($tenant))->toBe(100)->and(UsageEvent::query()->count())->toBe(1);

    Storage::disk('tw')->put("documents/{$tenant->id}/b.pdf", str_repeat('b', 50));
    resolve(MeasureStorage::class)->handle();

    expect(storageUsed($tenant))->toBe(150);
});

it('starts a new month from what is still there, without anyone uploading', function (): void {
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", str_repeat('a', 100));
    resolve(MeasureStorage::class)->handle();

    $this->travelTo('2026-12-02 12:00:00');
    resolve(MeasureStorage::class)->handle();

    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->orderBy('period_start')->pluck('used')->all())->toBe([100, 100]);
});

it('brings a company back to zero when its files are gone', function (): void {
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", str_repeat('a', 100));
    resolve(MeasureStorage::class)->handle();

    Storage::disk('tw')->deleteDirectory("documents/{$tenant->id}");
    resolve(MeasureStorage::class)->handle();

    expect(storageUsed($tenant))->toBe(0);
});

it('measures against the 5 GB of the plan and only measures: past it nothing is deleted or refused', function (): void {
    [, $account] = paidCompany();
    $tenant = planFor($account);
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", 'x');
    UsageCounter::query()->create(['tenant_id' => $tenant->id, 'meter' => STORAGE_METER, 'period_start' => '2026-11-01', 'used' => 5_368_709_121, 'reserved' => 0, 'quota' => 0]);

    $snapshot = resolve(UsageMeter::class)->snapshot($tenant->id, STORAGE_METER);

    expect($snapshot->quota)->toBe(5_368_709_120)->and($snapshot->remaining())->toBe(0)
        ->and(Storage::disk('tw')->exists("documents/{$tenant->id}/a.pdf"))->toBeTrue();
});

it('writes one log line with counts and never a path or a file name', function (): void {
    Log::spy();
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/secret-name.pdf", 'x');

    resolve(MeasureStorage::class)->handle();

    Log::shouldHaveReceived('info')->with('usage.storage_measured', ['companies' => 1, 'bytes' => 1])->once();
});

it('runs from the billing:measure-storage command', function (): void {
    $tenant = Tenant::factory()->create();
    Storage::disk('tw')->put("documents/{$tenant->id}/a.pdf", 'xx');

    $this->artisan('billing:measure-storage')->expectsOutputToContain('Empresas medidas: 1')->assertSuccessful();

    expect(storageUsed($tenant))->toBe(2);
});
