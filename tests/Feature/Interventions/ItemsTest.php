<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Actions\AddManualItem;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\RecordFollowUp;
use App\Modules\Interventions\Actions\SaveAnalysis;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\GuideCatalog;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(AddManualItem::class, SaveAnalysis::class, RecordFollowUp::class, GuideCatalog::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
});

it('lists the final score, the categories and the domains of the guide of the evaluation', function (string $guide, int $categories, int $domains): void {
    $groups = resolve(GuideCatalog::class)->groups($guide);

    expect(array_values(array_unique(array_map(fn (array $group): string => $group['scope']->value, $groups))))->toBe(['final', 'category', 'domain'])
        ->and(count(array_filter($groups, fn (array $group): bool => $group['scope'] === ItemScope::Category)))->toBe($categories)
        ->and(count(array_filter($groups, fn (array $group): bool => $group['scope'] === ItemScope::Domain)))->toBe($domains)
        ->and($groups[0])->toBe(['scope' => ItemScope::Final, 'key' => 'final', 'label' => 'Calificación final', 'category' => null]);
})->with([['nom035.guide-ii', 4, 8], ['nom035.guide-iii', 5, 10]]);

it('adds a row HR chooses from the catalog, without a level or figures', function (): void {
    $item = resolve(AddManualItem::class)->handle($this->program->id, ItemScope::Domain, 'lack_of_control');

    expect($item->origin)->toBe(ItemOrigin::Manual)
        ->and($item->highest_level)->toBeNull()
        ->and($item->scope)->toBe(ItemScope::Domain)
        ->and($item->scope_key)->toBe('lack_of_control')
        ->and($item->label)->toBe('Falta de control sobre el trabajo')
        ->and($item->category_key)->toBe('activity_factors');
});

it('adds rows to a Programa under the minimum, where nothing was derived', function (): void {
    [$few] = closedEvaluationInAreas($this->tenant, ['Logística' => 4]);
    $empty = resolve(CreateProgram::class)->handle($few->id, null);

    $item = resolve(AddManualItem::class)->handle($empty?->id ?? '', ItemScope::Category, 'work_time');

    expect($item->program_id)->toBe($empty?->id)
        ->and(ProgramItem::query()->where('program_id', $empty?->id)->count())->toBe(1);
});

it('refuses a row that is not in the catalog or already in the Programa', function (ItemScope $scope, string $key): void {
    expect(fn () => resolve(AddManualItem::class)->handle($this->program->id, $scope, $key))->toThrow(ValidationException::class);
})->with([
    'a key that does not exist' => [ItemScope::Domain, 'nonsense'],
    'a domain named as a category' => [ItemScope::Category, 'workload'],
    'a row already derived from the results' => [ItemScope::Final, 'final'],
]);

it('does not find the Programa of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Program::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(AddManualItem::class)->handle($foreign->id, ItemScope::Domain, 'workload'))->toThrow(ModelNotFoundException::class);
});

it('writes the analysis of a row, and clears it', function (): void {
    resolve(SaveAnalysis::class)->handle($this->item->id, "  La carga se concentra en producción.\n");

    expect($this->item->fresh()?->analysis)->toBe('La carga se concentra en producción.');

    resolve(SaveAnalysis::class)->handle($this->item->id, '   ');
    expect($this->item->fresh()?->analysis)->toBeNull();
});

it('does not find the row of another company to write its analysis', function (): void {
    $other = Tenant::factory()->create();
    $foreign = ProgramItem::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(SaveAnalysis::class)->handle($foreign->id, 'x'))->toThrow(ModelNotFoundException::class);
});

it('logs the analysis and the new row with ids, never the text', function (): void {
    Log::spy();

    resolve(SaveAnalysis::class)->handle($this->item->id, 'Análisis de Juan Pérez.');
    $added = resolve(AddManualItem::class)->handle($this->program->id, ItemScope::Domain, 'lack_of_control');

    Log::shouldHaveReceived('info')->with('intervention.analysis_saved', ['item_id' => $this->item->id, 'program_id' => $this->program->id, 'written' => true])->once();
    Log::shouldHaveReceived('info')->with('intervention.item_added', ['item_id' => $added->id, 'program_id' => $this->program->id, 'scope' => 'domain'])->once();
});

it('schedules and records the follow-up evaluation of a row (num. 8.4 e)', function (): void {
    resolve(RecordFollowUp::class)->handle($this->item->id, '2027-06-01', null, null, null);
    $scheduled = $this->item->fresh();

    expect($scheduled?->follow_up_due_on?->toDateString())->toBe('2027-06-01')
        ->and($scheduled?->follow_up_done_on)->toBeNull();

    resolve(RecordFollowUp::class)->handle($this->item->id, '2027-06-01', '2026-11-20', 'La carga bajó a nivel bajo.', null);
    $done = $this->item->fresh();

    expect($done?->follow_up_done_on?->toDateString())->toBe('2026-11-20')
        ->and($done?->follow_up_result)->toBe('La carga bajó a nivel bajo.');
});

it('needs a result to record the follow-up, and not a day in the future', function (?string $doneOn, ?string $result, string $field): void {
    $keys = [];

    try {
        resolve(RecordFollowUp::class)->handle($this->item->id, null, $doneOn, $result, null);
    } catch (ValidationException $invalid) {
        $keys = array_keys($invalid->errors());
    }

    expect($keys)->toBe([$field])
        ->and($this->item->fresh()?->follow_up_done_on)->toBeNull();
})->with([
    'no result' => ['2026-11-20', null, 'follow_up_result'],
    'a blank result' => ['2026-11-20', '  ', 'follow_up_result'],
    'tomorrow' => ['2026-11-21', 'Listo', 'follow_up_done_on'],
]);

it('links the follow-up to a later closed evaluation of the same work center, and to no other', function (): void {
    $center = $this->campaign->work_center_id;
    $later = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center, 'closed_at' => '2028-11-01 12:00:00']);
    $earlier = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center, 'closed_at' => '2020-11-01 12:00:00']);
    $elsewhere = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2028-11-01 12:00:00']);
    $running = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center]);
    $this->campaign->update(['closed_at' => '2026-11-19 18:00:00']);

    resolve(RecordFollowUp::class)->handle($this->item->id, null, '2026-11-20', 'Mejoró.', $later->id);
    expect($this->item->fresh()?->follow_up_campaign_id)->toBe($later->id);

    foreach ([$earlier->id, $elsewhere->id, $running->id, $this->campaign->id, '00000000-0000-4000-8000-000000000000'] as $id) {
        expect(fn () => resolve(RecordFollowUp::class)->handle($this->item->id, null, '2026-11-20', 'Mejoró.', $id))->toThrow(ValidationException::class);
    }
});

it('does not find the row of another company to record its follow-up', function (): void {
    $other = Tenant::factory()->create();
    $foreign = ProgramItem::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(RecordFollowUp::class)->handle($foreign->id, '2027-01-01', null, null, null))->toThrow(ModelNotFoundException::class);
});

it('logs the follow-up with ids and flags', function (): void {
    Log::spy();

    resolve(RecordFollowUp::class)->handle($this->item->id, '2027-06-01', '2026-11-20', 'Listo, Juan.', null);

    Log::shouldHaveReceived('info')->with('intervention.follow_up_recorded', ['item_id' => $this->item->id, 'program_id' => $this->program->id, 'done' => true, 'with_evaluation' => false])->once();
});

it('keeps the level of the row as the results gave it', function (): void {
    resolve(SaveAnalysis::class)->handle($this->item->id, 'Análisis');

    expect($this->item->fresh()?->highest_level)->toBe(RiskLevel::VeryHigh);
});
