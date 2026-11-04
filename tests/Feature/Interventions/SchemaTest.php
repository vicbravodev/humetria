<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * The integrity of the Programa de intervención lives in the database too (CLAUDE.md): each rule of the norm that a column can
 * hold is a check, tried here with direct inserts.
 */
/**
 * Inserts a row as the factory builds it, with `$attributes` on top, straight into the table: the model's casts would refuse
 * an unknown value before the database could.
 *
 * @param  Factory<Illuminate\Database\Eloquent\Model>  $factory
 * @param  array<string, mixed>  $attributes
 */
function insertRaw(Factory $factory, array $attributes): void
{
    $table = $factory->newModel()->getTable();
    $row = array_map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value, $factory->raw($attributes));

    DB::table($table)->insert([...$row, 'id' => (string) Str::uuid(), ...array_filter(['created_at' => now(), 'updated_at' => now()], fn (string $column): bool => Schema::hasColumn($table, $column), ARRAY_FILTER_USE_KEY)]);
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->in = ['tenant_id' => $this->tenant->id];
    workIn($this->tenant);
});

it('stores a Programa with its rows, actions, areas and progress', function (): void {
    $action = ProgramAction::factory()->create($this->in);
    $area = Area::factory()->create($this->in);
    ActionArea::factory()->create([...$this->in, 'action_id' => $action->id, 'area_id' => $area->id]);
    ProgressEntry::factory()->create([...$this->in, 'action_id' => $action->id]);

    expect(Program::query()->count())->toBe(1)
        ->and(ProgramItem::query()->count())->toBe(1)
        ->and($action->level->value)->toBe(1)
        ->and($action->kind)->toBe(ActionKind::PolicyReview)
        ->and($action->status)->toBe(ActionStatus::Planned)
        ->and(ActionArea::query()->count())->toBe(1)
        ->and(ProgressEntry::query()->count())->toBe(1)
        ->and(Program::query()->sole()->highest_level)->toBe(RiskLevel::Medium);
});

it('allows one Programa per evaluation', function (): void {
    $program = Program::factory()->create($this->in);

    expect(fn () => insertRaw(Program::factory(), [...$this->in, 'campaign_id' => $program->campaign_id]))->toThrow(QueryException::class, 'intervention_programs_campaign_id_unique');
});

it('rejects a Programa below Medio, since the norm asks for none there', function (): void {
    expect(fn () => insertRaw(Program::factory(), [...$this->in, 'highest_level' => 'low']))->toThrow(QueryException::class, 'intervention_programs_level_known');
});

it('rejects a responsible user without a name', function (): void {
    expect(fn () => insertRaw(Program::factory(), [...$this->in, 'responsible_user_id' => User::factory()->create()->id, 'responsible_name' => null]))->toThrow(QueryException::class, 'intervention_programs_user_has_name');
});

it('allows one row per final score, category or domain in a Programa', function (): void {
    $item = ProgramItem::factory()->create($this->in);

    expect(fn () => insertRaw(ProgramItem::factory(), [...$this->in, 'program_id' => $item->program_id, 'scope' => $item->scope, 'scope_key' => $item->scope_key]))->toThrow(QueryException::class, 'intervention_items_program_id_scope_scope_key_unique');
});

it('rejects a row from Results without a level and a manual one with a level', function (array $attributes, string $constraint): void {
    expect(fn () => insertRaw(ProgramItem::factory(), [...$this->in, ...$attributes]))->toThrow(QueryException::class, $constraint);
})->with([
    'results without level' => [['origin' => 'results', 'highest_level' => null], 'intervention_items_results_have_level'],
    'manual with level' => [['origin' => 'manual', 'highest_level' => 'high'], 'intervention_items_results_have_level'],
    'unknown scope' => [['scope' => 'other'], 'intervention_items_scope_known'],
    'follow-up done without a result' => [['follow_up_done_on' => '2026-11-20', 'follow_up_result' => '  '], 'intervention_items_follow_up_has_result'],
]);

it('rejects an action that breaks the rules of 8.4 and 8.5', function (array $attributes, string $constraint): void {
    expect(fn () => insertRaw(ProgramAction::factory(), [...$this->in, ...$attributes]))->toThrow(QueryException::class, $constraint);
})->with([
    'third level without a professional' => [['level' => 3, 'kind' => 'clinical_care', 'professional' => null], 'intervention_actions_third_level_has_professional'],
    'a professional on the first level' => [['level' => 1, 'professional' => 'psychologist'], 'intervention_actions_third_level_has_professional'],
    'clinical care on the first level' => [['level' => 1, 'kind' => 'clinical_care'], 'intervention_actions_clinical_is_third_level'],
    'third level that is not clinical care' => [['level' => 3, 'kind' => 'training', 'professional' => 'physician'], 'intervention_actions_clinical_is_third_level'],
    'unknown level' => [['level' => 4], 'intervention_actions_level_known'],
    'unknown kind' => [['kind' => 'magic'], 'intervention_actions_kind_known'],
    'unknown professional' => [['level' => 3, 'kind' => 'clinical_care', 'professional' => 'nurse'], 'intervention_actions_professional_known'],
    'due before it starts' => [['starts_on' => '2026-12-10', 'due_on' => '2026-12-09'], 'intervention_actions_due_not_before_start'],
    'done without its day' => [['status' => 'done', 'completed_on' => null], 'intervention_actions_done_is_dated'],
    'a day without being done' => [['status' => 'planned', 'completed_on' => '2026-12-01'], 'intervention_actions_done_is_dated'],
    'cancelled without a reason' => [['status' => 'cancelled', 'cancelled_reason' => ' '], 'intervention_actions_cancelled_has_reason'],
    'without a responsible' => [['responsible_name' => ' '], 'intervention_actions_responsible_present'],
    'without a title' => [['title' => ''], 'intervention_actions_title_present'],
    'unknown subjects' => [['subjects' => 'people'], 'intervention_actions_subjects_known'],
]);

it('accepts the third level with a professional, and a due day equal to the start', function (): void {
    $clinical = ProgramAction::factory()->clinical()->create([...$this->in, 'starts_on' => '2026-12-09', 'due_on' => '2026-12-09']);
    $cancelled = ProgramAction::factory()->cancelled()->create($this->in);

    expect($clinical->professional?->value)->toBe('psychologist')
        ->and($cancelled->status)->toBe(ActionStatus::Cancelled)
        ->and(ProgramAction::factory()->done()->create($this->in)->completed_on)->not->toBeNull();
});

it('does not link an action to the row of another company', function (): void {
    $other = Tenant::factory()->create();
    $item = ProgramItem::factory()->create($this->in);

    expect(fn () => insertRaw(ProgramAction::factory(), ['tenant_id' => $other->id, 'program_id' => $item->program_id, 'item_id' => $item->id]))->toThrow(QueryException::class, 'foreign key');
});

it('does not name an area of another company', function (): void {
    $action = ProgramAction::factory()->create($this->in);
    $foreign = Area::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => insertRaw(ActionArea::factory(), [...$this->in, 'action_id' => $action->id, 'area_id' => $foreign->id]))->toThrow(QueryException::class, 'foreign key');
});

it('names an area once per action', function (): void {
    $action = ProgramAction::factory()->create($this->in);
    $area = Area::factory()->create($this->in);
    ActionArea::factory()->create([...$this->in, 'action_id' => $action->id, 'area_id' => $area->id]);

    expect(fn () => insertRaw(ActionArea::factory(), [...$this->in, 'action_id' => $action->id, 'area_id' => $area->id]))->toThrow(QueryException::class, 'intervention_action_areas_action_id_area_id_unique');
});

it('rejects a progress entry with an unknown status', function (): void {
    expect(fn () => insertRaw(ProgressEntry::factory(), [...$this->in, 'status' => 'finished']))->toThrow(QueryException::class, 'intervention_progress_entries_status_known');
});

it('never edits or deletes a progress entry (criterio C-35)', function (): void {
    $entry = ProgressEntry::factory()->create([...$this->in, 'note' => 'Se difundió la política.']);

    expect(fn () => $entry->update(['note' => 'Otra']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(ProgressEntry::query()->sole()->note)->toBe('Se difundió la política.')
        ->and(Schema::hasColumn('intervention_progress_entries', 'updated_at'))->toBeFalse();
});

it('keeps no link from the Programa to a person, an invitation or a token (criterios C-22 and C-33)', function (): void {
    $tables = array_values(array_filter(array_map(fn (array $table): string => $table['name'], Schema::getTables()), fn (string $name): bool => str_starts_with($name, 'intervention_')));
    $forbidden = ['employee', 'participant', 'invitation', 'token', 'response', 'answer'];

    expect($tables)->toHaveCount(5);

    foreach ($tables as $table) {
        foreach (Schema::getColumnListing($table) as $column) {
            foreach ($forbidden as $word) {
                expect($column)->not->toContain($word);
            }
        }

        foreach (Schema::getForeignKeys($table) as $foreign) {
            expect($foreign['foreign_table'])->not->toBeIn(['employees', 'campaign_participants', 'participant_instruments', 'anonymous_responses', 'trauma_screenings']);
        }
    }
});
