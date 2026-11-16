<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Actions\OpenWorkersPage;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Disclosure\Support\AckProgress;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;

/*
 * The disclosure story's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'tells the disclosure story: a policy with its blocks in every center, confirmed by all but the door, results published and told' => function (): void {
        $tenantId = resolve(DemoCompanies::class)->id();
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $centers = collect(resolve(WorkCenters::class)->all())->pluck('id')->sort()->values()->all();
        $byId = collect(DemoRoster::people())->keyBy(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key));
        $door = $byId->filter(fn (DemoPerson $person): bool => $person->reservedForDoor);

        // publishes one version of the policy, in force in every center, with its responsibles and resources and a, b and c
        $policy = Document::query()->where('kind', DocumentKind::Policy)->sole();
        $text = array_column($policy->content, 'text', 'key');
        $inForce = resolve(PolicyStanding::class)->inForce($centers);
        expect($policy->status)->toBe(DocumentStatus::Published)
            ->and($policy->version)->toBe(1)
            ->and($policy->covers)->toBe(['a', 'b', 'c'])
            ->and(trim($text['responsibles']))->not->toBe('')
            ->and(trim($text['resources']))->not->toBe('')
            ->and($policy->published_at?->lessThan($today->subMonths(6)))->toBeTrue()
            ->and(array_keys($inForce))->toEqualCanonicalizing($centers)
            ->and(collect($inForce)->every(fn ($fact): bool => $fact->documentId === $policy->id))->toBeTrue();

        // publishes the blocks of 5.7 the company writes (b, c, d and f) to every center, each with its text
        $blocks = Document::query()->where('kind', '!=', DocumentKind::Policy)->get();
        expect($blocks->map(fn (Document $block): string => $block->kind->inciso())->sort()->values()->all())->toBe(['b', 'c', 'd', 'f'])
            ->and($blocks->every(fn (Document $block): bool => $block->status === DocumentStatus::Published && trim($block->content[0]['text']) !== ''))->toBeTrue();
        foreach ($blocks as $block) {
            expect(DocumentCenter::query()->where('document_id', $block->id)->pluck('work_center_id')->sort()->values()->all())->toBe($centers);
        }

        // asks everyone; everyone but the door's people confirmed it, on paper or from their link, days after it came out
        $acks = Acknowledgment::query()->where('document_id', $policy->id)->get()->keyBy('employee_id');
        $confirmed = $acks->whereNotNull('acknowledged_at');
        $progress = resolve(AckProgress::class)->of($policy);
        expect($acks->keys()->sort()->values()->all())->toBe($byId->keys()->sort()->values()->all())
            ->and($confirmed->keys()->sort()->values()->all())->toBe($byId->reject(fn (DemoPerson $person): bool => $person->reservedForDoor)->keys()->sort()->values()->all())
            ->and($progress['active'])->toBe(180)
            ->and($progress['confirmed'])->toBe(180 - $door->count())
            ->and($progress['percent'])->toBe(77)
            ->and($confirmed->pluck('channel')->unique()->sort()->values()->all())->toBe(['manual', 'portal'])
            ->and($confirmed->every(fn (Acknowledgment $ack): bool => $ack->acknowledged_at?->greaterThanOrEqualTo($policy->published_at->startOfDay()) === true && $ack->acknowledged_at->lessThan($today)))->toBeTrue();
        // nobody confirms before they joined: whoever came after the policy confirms from their link days after their hire
        expect($confirmed->every(fn (Acknowledgment $ack): bool => $ack->acknowledged_at?->greaterThan($today->subDays($byId[$ack->employee_id]->tenureDays)) === true))->toBeTrue();

        // a visitor of the door confirms the policy from their own portal
        $visitor = $door->keys()->first();
        $token = resolve(PortalAccess::class)->token($visitor);
        $this->get(route('portal.policy.show', $token))->assertOk();
        $this->post(route('portal.policy.acknowledge', $token), ['document' => $policy->id])->assertRedirect(route('portal.policy.show', $token));
        expect(Acknowledgment::query()->where('document_id', $policy->id)->where('employee_id', $visitor)->sole()->channel)->toBe('portal');

        // publishes the results of every closed evaluation that has them (not the Guía I alone), the plant's with its measures
        $closed = Campaign::query()->where('kind', CampaignKind::Nom035Evaluation)->whereNotNull('closed_at')->get()->keyBy('work_center_id');
        $publications = ResultPublication::query()->whereNull('withdrawn_at')->get()->keyBy('work_center_id');
        $monterrey = collect(resolve(WorkCenters::class)->all())->firstWhere('name', DemoRoster::CENTERS['monterrey'])?->id;
        $plant = collect(resolve(WorkCenters::class)->all())->firstWhere('name', DemoRoster::CENTERS['plant'])?->id;
        expect($publications->keys()->sort()->values()->all())->toBe($closed->keys()->reject(fn (string $id): bool => $id === $monterrey)->sort()->values()->all())
            ->and($publications->count())->toBe(3)
            ->and($publications->every(fn (ResultPublication $publication): bool => $publication->campaign_id === $closed[$publication->work_center_id]->id
                && $publication->published_at->greaterThan($closed[$publication->work_center_id]->closed_at)))->toBeTrue()
            ->and($publications[$plant]->show_measures)->toBeTrue();

        // records how each center was told, in the past, every inciso of 5.7 reaching every center
        $records = DisseminationRecord::query()->get();
        foreach ($centers as $center) {
            $told = $records->where('work_center_id', $center)->flatMap(fn (DisseminationRecord $record): array => $record->covered_items)->unique()->sort()->values()->all();
            expect($told)->toBe($center === $monterrey ? ['a', 'b', 'c', 'd', 'f'] : ['a', 'b', 'c', 'd', 'e', 'f']);
        }
        expect($records->every(fn (DisseminationRecord $record): bool => $record->done_on->lessThan($today) && $record->evidence_path === null))->toBeTrue();

        // opens the page of the workers with its permanent link
        $link = PageLink::query()->sole();
        $token = $link->token();
        expect($token)->not->toBeNull()
            ->and(resolve(OpenWorkersPage::class)->handle((string) $token)->tenantId)->toBe($tenantId);

        // nothing outside the demo company
        foreach ([Document::class, DocumentCenter::class, Acknowledgment::class, DisseminationRecord::class, PageLink::class, ResultPublication::class] as $model) {
            expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
        }
    },
];
