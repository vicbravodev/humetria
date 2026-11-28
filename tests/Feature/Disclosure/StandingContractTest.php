<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Disclosure\Support\CompanyDisclosureStanding;
use App\Modules\Disclosure\Support\Days;
use App\Modules\Disclosure\Support\DeliveryStanding;
use App\Modules\Disclosure\Support\ResultsStanding;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyDisclosureStanding::class, Days::class, DeliveryStanding::class, ResultsStanding::class);

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

it('sets the published results nobody can reach apart from the ones the people can see', function (): void {
    $published = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $covered = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    $unpublished = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $published->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $covered->id]);
    DisseminationRecord::factory()->covering(['e'])->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);

    expect(app(DisclosureStanding::class)->sharing([$published->id, $covered->id, $unpublished->id]))->toBe(['shared' => [$covered->id], 'unseen' => [$published->id]])
        ->and(app(DisclosureStanding::class)->sharing([]))->toBe(['shared' => [], 'unseen' => []]);
});

it('sums up the policy in force in each center: its version, the day it was published and how many of its people confirmed it', function (): void {
    $this->travelTo('2026-11-03 18:00:00');
    $policy = Document::factory()->published()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_at' => '2026-11-03 04:00:00']);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);
    $people = Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    Acknowledgment::factory()->portal()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $people[0]->id]);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $people[1]->id]);

    expect(app(DisclosureStanding::class)->policySummaries([$this->plant->id, $this->offices->id]))
        ->toBe([$this->plant->id => ['version' => 2, 'publishedOn' => '2 nov 2026', 'confirmed' => 1, 'expected' => 3]]);
});

it('counts nobody in a center whose policy reached it before it had people', function (): void {
    $policy = Document::factory()->published()->create(['tenant_id' => $this->tenant->id, 'version' => 1]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->offices->id]);

    expect(app(DisclosureStanding::class)->policySummaries([$this->offices->id])[$this->offices->id])->toMatchArray(['confirmed' => 0, 'expected' => 0]);
});

it('tells the day the results of each evaluation were published, and nothing for the withdrawn ones', function (): void {
    $published = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $withdrawn = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->offices->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $published->id, 'created_at' => '2026-10-05 15:00:00']);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $withdrawn->id, 'withdrawn_at' => now(), 'withdrawn_by' => ownerOf($this->tenant)->id]);

    expect(app(DisclosureStanding::class)->publishedOn([$published->id, $withdrawn->id]))->toBe([$published->id => '5 oct 2026']);
});

it('asks nothing more when none of the evaluations has its results published', function (): void {
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->expectsDatabaseQueryCount(1);
    expect(app(DisclosureStanding::class)->sharing([$campaign->id]))->toBe(['shared' => [], 'unseen' => []]);
});
