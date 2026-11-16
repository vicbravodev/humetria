<?php

declare(strict_types=1);

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Disclosure\Support\DisclosureFiles;
use App\Modules\Interventions\Support\InterventionFiles;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    foreach (['telework_files', 'seating_files', 'intervention_files', 'disclosure_files'] as $key) {
        config(["filesystems.{$key}" => "fake-{$key}"]);
        Storage::fake("fake-{$key}");
    }
    config(['billing.versions.v1.plans.cumplimiento.quotas.storage_bytes' => 100_000_000]);
    $this->tenant = Tenant::factory()->create();
    [, $account] = paidCompany();
    planFor($account, [], $this->tenant);
    workIn($this->tenant);
    $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);
    $this->centerId = $teleworker->work_center_id;
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $this->token = resolve(IssuePortalLink::class)->handle($employee->id);
    $this->used = fn (): int => (int) UsageCounter::query()->where('tenant_id', $this->tenant->id)->where('meter', 'storage_bytes')->sum('used');
    $this->png = fn (string $name = 'lugar.png'): UploadedFile => new UploadedFile(__DIR__.'/../../Fixtures/photo.png', $name, null, null, true);
});

it('stores nothing and takes no quota when the evidence request is not the person\'s', function (): void {
    $this->post(route('portal.evidence.store', [$this->token, (string) Str::uuid()]), ['photo_general_0' => ($this->png)(), 'photo_general_1' => ($this->png)('b.png')])->assertNotFound();

    expect(($this->used)())->toBe(0)->and(Storage::disk('fake-telework_files')->allFiles())->toBe([]);
});

it('stores nothing and takes no quota when the photos were already sent', function (): void {
    $request = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'fulfilled_at' => now()]);

    $this->post(route('portal.evidence.store', [$this->token, $request->id]), ['photo_general_0' => ($this->png)()])->assertSessionHasErrors('evidence');

    expect(($this->used)())->toBe(0)->and(Storage::disk('fake-telework_files')->allFiles())->toBe([]);
});

it('stores nothing and takes no quota when the checklist was already sent', function (): void {
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->centerId]);
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'checklist_id' => $checklist->id]);

    $this->post(route('portal.checklist.store', [$this->token, $application->id]), ['consent' => '1', 'answers' => ['x' => 'no'], 'photo_general_0' => ($this->png)()])->assertSessionHasErrors('application');

    expect(($this->used)())->toBe(0)->and(Storage::disk('fake-telework_files')->allFiles())->toBe([]);
});

it('gives back the quota of a file a rollback deletes, in the four modules', function (string $store, string $disk): void {
    $path = "documents/{$this->tenant->id}/a.pdf";
    resolve($store)->put($path, str_repeat('x', 300));
    expect(($this->used)())->toBe(300);

    resolve($store)->discard($path, "documents/{$this->tenant->id}/missing.pdf");

    expect(($this->used)())->toBe(0)->and(Storage::disk($disk)->exists($path))->toBeFalse();
})->with([
    'telework' => [FileStore::class, 'fake-telework_files'],
    'seating' => [SeatingFiles::class, 'fake-seating_files'],
    'interventions' => [InterventionFiles::class, 'fake-intervention_files'],
    'disclosure' => [DisclosureFiles::class, 'fake-disclosure_files'],
]);
