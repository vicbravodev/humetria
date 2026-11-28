<?php

declare(strict_types=1);

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Disclosure\Support\DisclosureFiles;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

mutates(DisclosureFiles::class);

// The private disk of the evidence of difusión: every byte stored counts against the plan, and a rollback gives it back.

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    config(['filesystems.disclosure_files' => 'fake-disclosure-files', 'billing.versions.v2.plans.cumplimiento.quotas.storage_bytes' => 1000]);
    Storage::fake('fake-disclosure-files');
    [, $account] = paidCompany();
    $this->tenant = planFor($account);
    $this->path = "documents/{$this->tenant->id}/acuse.pdf";
});

function disclosureBytesUsed(Tenant $tenant): int
{
    return (int) UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'storage_bytes')->sum('used');
}

it('gives back the bytes of what a rollback discards and deletes it, and lets a missing path through', function (): void {
    $files = resolve(DisclosureFiles::class);
    $files->put($this->path, str_repeat('x', 300));
    $files->put("documents/{$this->tenant->id}/otro.pdf", str_repeat('x', 200));

    $files->discard($this->path, "documents/{$this->tenant->id}/nunca.pdf");

    expect(disclosureBytesUsed($this->tenant))->toBe(200);
    Storage::disk('fake-disclosure-files')->assertMissing($this->path);
    Storage::disk('fake-disclosure-files')->assertExists("documents/{$this->tenant->id}/otro.pdf");
});

it('deletes the files it is given, and a missing one is fine', function (): void {
    $files = resolve(DisclosureFiles::class);
    $files->put($this->path, 'x');

    $files->delete($this->path, "documents/{$this->tenant->id}/nunca.pdf");

    Storage::disk('fake-disclosure-files')->assertMissing($this->path);
});

it('gives the bytes back when the disk fails to write', function (): void {
    Storage::shouldReceive('disk')->andReturn(Mockery::mock(FilesystemAdapter::class, fn ($mock) => $mock->shouldReceive('put')->andReturn(false)));

    expect(fn () => resolve(DisclosureFiles::class)->put($this->path, str_repeat('x', 100)))->toThrow(RuntimeException::class, 'The file could not be stored.');

    expect(disclosureBytesUsed($this->tenant))->toBe(0);
});
