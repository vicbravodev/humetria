<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

mutates(CreateProgram::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** @return list<array{string, string, string, string|null, RiskLevel}> */
function expectedFoci(Campaign $campaign): array
{
    $record = resolve(CampaignRecords::class)->find($campaign->id);
    expect($record)->toBeInstanceOf(CampaignRecord::class);

    return array_map(fn ($focus): array => [$focus->scope, $focus->key, $focus->label, $focus->category, $focus->level], resolve(RiskResults::class)->forCampaign($record)->needingProgram());
}

it('drafts the Programa of a closed Guía II with a row for each place the results put in Medio or more', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'never'));
    $user = User::factory()->create();

    $program = resolve(CreateProgram::class)->handle($campaign->id, $user->id);

    $rows = ProgramItem::query()->where('program_id', $program?->id)->get()
        ->mapWithKeys(fn (ProgramItem $item): array => ["{$item->scope->value}:{$item->scope_key}" => [$item->label, $item->category_key, $item->highest_level, $item->origin]])->sortKeys()->all();
    $expected = collect(expectedFoci($campaign))
        ->mapWithKeys(fn (array $focus): array => ["{$focus[0]}:{$focus[1]}" => [$focus[2], $focus[3], $focus[4], ItemOrigin::Results]])->sortKeys()->all();

    expect($program)->toBeInstanceOf(Program::class)
        ->and($program->campaign_id)->toBe($campaign->id)
        ->and($program->highest_level)->toBe(RiskLevel::VeryHigh)
        ->and($program->responsible_name)->toBeNull()
        ->and($program->created_by)->toBe($user->id)
        ->and($rows)->not->toBeEmpty()->toEqual($expected);
});

it('drafts the Programa of a Guía III in the same way', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-iii', 'always'), guide: 'nom035.guide-iii');

    $program = resolve(CreateProgram::class)->handle($campaign->id, null);

    expect($program?->created_by)->toBeNull()
        ->and(ProgramItem::query()->count())->toBe(count(expectedFoci($campaign)))
        ->and(ProgramItem::query()->where('scope', 'domain')->count())->toBeGreaterThan(0);
});

it('creates no Programa when nobody falls in Medio or more: the norm asks for none (num. 8.3)', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035HealthyAnswers('nom035.guide-ii'));

    expect(resolve(CreateProgram::class)->handle($campaign->id, null))->toBeNull()
        ->and(Program::query()->count())->toBe(0);
});

it('drafts an empty Programa when the questionnaires are under the minimum, since nothing may be derived from them', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'), count: 4);

    $program = resolve(CreateProgram::class)->handle($campaign->id, null);

    expect($program)->toBeInstanceOf(Program::class)
        ->and($program->highest_level)->toBeNull()
        ->and(ProgramItem::query()->count())->toBe(0);
});

it('derives rows from 5 questionnaires and none from 4', function (int $count, int $rows): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'), count: $count);

    resolve(CreateProgram::class)->handle($campaign->id, null);

    expect(ProgramItem::query()->count() > 0)->toBe($rows > 0);
})->with([[4, 0], [5, 1]]);

it('refuses an evaluation that still runs, one of only the Guía I, and one that does not exist', function (): void {
    $open = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id]);
    $trauma->update(['status' => 'closed', 'closed_at' => now()]);

    foreach ([$open->id, $trauma->id, '00000000-0000-4000-8000-000000000000', 'not-an-id'] as $id) {
        expect(fn () => resolve(CreateProgram::class)->handle($id, null))->toThrow(ProgramUnavailable::class);
    }

    expect(Program::query()->count())->toBe(0);
});

it('refuses the evaluation of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = closedEvaluationWith($other, nom035Answers('nom035.guide-ii', 'always'));

    expect(fn () => resolve(CreateProgram::class)->handle($foreign->id, null))->toThrow(ProgramUnavailable::class);

    workIn($other);
    expect(Program::query()->count())->toBe(0);
});

it('is idempotent: a second call finds the Programa and writes nothing', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));
    $first = resolve(CreateProgram::class)->handle($campaign->id, null);
    $rows = ProgramItem::query()->count();

    $second = resolve(CreateProgram::class)->handle($campaign->id, User::factory()->create()->id);

    expect($second?->id)->toBe($first?->id)
        ->and($second?->created_by)->toBeNull()
        ->and(Program::query()->count())->toBe(1)
        ->and(ProgramItem::query()->count())->toBe($rows)
        ->and(AuditEntry::query()->where('event', 'interventions.program_created')->count())->toBe(1);
});

it('returns the Programa another request made first when both race', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));
    $raced = false;
    // The other request commits before this one starts its own transaction.
    DB::beforeStartingTransaction(function () use ($campaign, &$raced): void {
        if (! $raced) {
            $raced = true;
            DB::table('intervention_programs')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'campaign_id' => $campaign->id, 'highest_level' => 'high', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $program = resolve(CreateProgram::class)->handle($campaign->id, null);

    expect($program?->highest_level)->toBe(RiskLevel::High)
        ->and(Program::query()->count())->toBe(1)
        ->and(ProgramItem::query()->count())->toBe(0);
});

it('logs and audits the draft without any text or figure of the results', function (): void {
    Log::spy();
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));

    $program = resolve(CreateProgram::class)->handle($campaign->id, null);

    Log::shouldHaveReceived('info')->with('intervention.program_drafted', ['program_id' => $program?->id, 'campaign_id' => $campaign->id, 'rows' => count(expectedFoci($campaign)), 'highest_level' => 'very_high', 'by_system' => true])->once();
    $entry = AuditEntry::query()->where('event', 'interventions.program_created')->sole();
    expect($entry->subject_id)->toBe($program?->id)
        ->and($entry->metadata)->toEqual(['campaign_id' => $campaign->id, 'by_system' => true]);
});
