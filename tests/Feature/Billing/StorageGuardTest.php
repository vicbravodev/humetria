<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\StorageGuard;
use App\Modules\Billing\Exceptions\StorageQuotaExceeded;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Support\LedgerStorageGuard;
use App\Modules\Disclosure\Support\DisclosureFiles;
use App\Modules\Interventions\Support\InterventionFiles;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

mutates(LedgerStorageGuard::class);

/** A company on a plan whose storage quota is exactly `$quota` bytes, with its fake private disk. */
function guardedCompany(int $quota): Tenant
{
    config(['billing.versions.v1.plans.cumplimiento.quotas.storage_bytes' => $quota]);
    [, $account] = paidCompany();

    return planFor($account);
}

function guardUsed(Tenant $tenant): int
{
    return (int) UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'storage_bytes')->sum('used');
}

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    foreach (['telework_files', 'seating_files', 'intervention_files', 'disclosure_files'] as $key) {
        config(["filesystems.{$key}" => "fake-{$key}"]);
        Storage::fake("fake-{$key}");
    }
});

it('lets a file in up to the quota and not one byte more: quota-1, exact and +1', function (int $quota, int $size, bool $fits): void {
    $tenant = guardedCompany($quota);
    $store = resolve(FileStore::class);

    if ($fits) {
        $store->put("documents/{$tenant->id}/a.pdf", str_repeat('x', $size));
        expect(Storage::disk('fake-telework_files')->exists("documents/{$tenant->id}/a.pdf"))->toBeTrue()->and(guardUsed($tenant))->toBe($size);
    } else {
        expect(fn () => $store->put("documents/{$tenant->id}/a.pdf", str_repeat('x', $size)))->toThrow(StorageQuotaExceeded::class);
        expect(Storage::disk('fake-telework_files')->exists("documents/{$tenant->id}/a.pdf"))->toBeFalse()->and(guardUsed($tenant))->toBe(0);
    }
})->with([
    'one under' => [1000, 999, true],
    'exactly the quota' => [1000, 1000, true],
    'one over' => [1000, 1001, false],
]);

it('counts what is already there: the second file is the one that does not fit', function (): void {
    $tenant = guardedCompany(1000);
    $store = resolve(FileStore::class);
    $store->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 600));

    expect(fn () => $store->put("documents/{$tenant->id}/b.pdf", str_repeat('x', 401)))->toThrow(StorageQuotaExceeded::class);
    $store->put("documents/{$tenant->id}/c.pdf", str_repeat('x', 400));

    expect(guardUsed($tenant))->toBe(1000);
});

it('guards the files of the four modules with the same rule', function (string $store): void {
    $tenant = guardedCompany(10);

    expect(fn () => resolve($store)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 11)))->toThrow(StorageQuotaExceeded::class);
    resolve($store)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 10));
    expect(guardUsed($tenant))->toBe(10);
})->with([FileStore::class, SeatingFiles::class, InterventionFiles::class, DisclosureFiles::class]);

it('keeps reading, streaming and deleting after the quota is full', function (): void {
    $tenant = guardedCompany(10);
    $store = resolve(FileStore::class);
    $store->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 10));

    expect(fn () => $store->put("documents/{$tenant->id}/b.pdf", 'x'))->toThrow(StorageQuotaExceeded::class);
    expect($store->stream("documents/{$tenant->id}/a.pdf")->getStatusCode())->toBe(200);
    $store->delete("documents/{$tenant->id}/a.pdf");

    expect(Storage::disk('fake-telework_files')->exists("documents/{$tenant->id}/a.pdf"))->toBeFalse();
});

it('does not block a company without a plan', function (): void {
    $tenant = Tenant::factory()->create();
    config(['billing.unplanned_quotas.storage_bytes' => 1]);

    resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 5000));

    expect(Storage::disk('fake-telework_files')->exists("documents/{$tenant->id}/a.pdf"))->toBeTrue();
});

it('keeps each company to its own bytes', function (): void {
    $a = guardedCompany(10);
    [, $account] = paidCompany();
    $b = planFor($account);

    resolve(FileStore::class)->put("documents/{$a->id}/a.pdf", str_repeat('x', 10));
    resolve(FileStore::class)->put("documents/{$b->id}/b.pdf", str_repeat('x', 10));

    expect(guardUsed($a))->toBe(10)->and(guardUsed($b))->toBe(10);
});

it('starts a new month from what was there, not from zero', function (): void {
    $tenant = guardedCompany(1000);
    resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 900));

    $this->travelTo('2026-12-02 12:00:00');

    expect(fn () => resolve(FileStore::class)->put("documents/{$tenant->id}/b.pdf", str_repeat('x', 101)))->toThrow(StorageQuotaExceeded::class);
    resolve(FileStore::class)->put("documents/{$tenant->id}/b.pdf", str_repeat('x', 100));
    expect(UsageCounter::query()->where('tenant_id', $tenant->id)->orderByDesc('period_start')->first()?->used)->toBe(1000);
});

it('gives the bytes back when the disk fails to write', function (): void {
    $tenant = guardedCompany(1000);
    Storage::shouldReceive('disk')->andReturn(Mockery::mock(Illuminate\Filesystem\FilesystemAdapter::class, fn ($mock) => $mock->shouldReceive('put')->andReturn(false)));

    expect(fn () => resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 100)))->toThrow(RuntimeException::class);

    expect(guardUsed($tenant))->toBe(0);
});

it('ignores a path that is not {type}/{company}/…', function (): void {
    resolve(StorageGuard::class)->admit('loose.txt', 5000);
    resolve(StorageGuard::class)->admit('documents/not-a-company/a.pdf', 5000);

    expect(UsageCounter::query()->count())->toBe(0);
});

it('tells the person the plan\'s size and what still works', function (): void {
    $tenant = guardedCompany(5_368_709_120);
    resolve(StorageGuard::class)->admit("documents/{$tenant->id}/a.pdf", 5_368_709_120);

    expect(fn () => resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", 'x'))
        ->toThrow(StorageQuotaExceeded::class, 'Tu plan incluye 5 GB de archivos')
        ->and(fn () => resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", 'x'))
        ->toThrow(StorageQuotaExceeded::class, 'leyendo, descargando y borrando');
});

it('answers a form with a toast and the same page, and a JSON client with a 422', function (): void {
    $tenant = guardedCompany(10);
    Route::post('probe-storage', fn () => resolve(FileStore::class)->put("documents/{$tenant->id}/a.pdf", str_repeat('x', 11)))->middleware('web');

    $this->from('/previous')->post('probe-storage')->assertRedirect('/previous');
    $this->postJson('probe-storage')->assertStatus(422)->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'archivos'));
});
