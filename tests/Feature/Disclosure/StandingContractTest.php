<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Disclosure\Support\CompanyDisclosureStanding;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyDisclosureStanding::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->offices = WorkCenter::factory()->for($this->tenant)->create();
});

it('tells which centers a published policy reached', function (): void {
    $policy = Document::factory()->published()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);
    // A published text that is not the policy, and a draft policy, reach nothing that counts.
    $block = Document::factory()->state(['kind' => DocumentKind::cases()[1]])->published()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $block->id, 'work_center_id' => $this->offices->id]);

    expect(app(DisclosureStanding::class)->withPolicy([$this->plant->id, $this->offices->id]))->toBe([$this->plant->id]);
});

it('tells which evaluations have their results published and in front of the people, and not withdrawn', function (): void {
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    $shared = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $withdrawn = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    $unshared = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $shared->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $withdrawn->id, 'withdrawn_at' => now(), 'withdrawn_by' => ownerOf($this->tenant)->id]);

    expect(app(DisclosureStanding::class)->sharedEvaluations([$shared->id, $withdrawn->id, $unshared->id]))->toBe([$shared->id])
        ->and(app(DisclosureStanding::class)->sharedEvaluations([]))->toBe([]);
});

it('does not count published results the people cannot see: no active page and no means that covers them', function (): void {
    $published = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $covered = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $published->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $covered->id]);
    // A means that covers other incisos does not deliver the results; one that covers «e» in that center does.
    DisseminationRecord::factory()->covering(['a'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    DisseminationRecord::factory()->covering(['e'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);

    expect(app(DisclosureStanding::class)->sharedEvaluations([$published->id, $covered->id]))->toBe([$covered->id]);
});
