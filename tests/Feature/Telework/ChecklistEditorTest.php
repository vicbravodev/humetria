<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Actions\AddQuestion;
use App\Modules\Telework\Actions\MoveQuestion;
use App\Modules\Telework\Actions\PublishChecklist;
use App\Modules\Telework\Actions\RemoveQuestion;
use App\Modules\Telework\Actions\StartChecklistDraft;
use App\Modules\Telework\Actions\UpdateQuestion;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\QuestionOrigin;
use App\Modules\Telework\Http\Controllers\ChecklistController;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistAccess;
use App\Modules\Telework\Support\Coverage;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AddQuestion::class, UpdateQuestion::class, RemoveQuestion::class, MoveQuestion::class, PublishChecklist::class, StartChecklistDraft::class, Coverage::class, ChecklistController::class, ChecklistAccess::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    $this->actingAs($this->hr)->post(route('tenant.telework.committee.store', [$this->tenant, $this->center->id]), ['constituted_on' => '2026-09-15', 'member_ids' => [$this->cora->id]])->assertSessionHasNoErrors();
    $this->actingAs($this->cora);
});

function draft(): Checklist
{
    workIn(test()->tenant);

    return Checklist::query()->where('status', ChecklistStatus::Draft)->sole();
}

function question(string $code, ?Checklist $checklist = null): ChecklistQuestion
{
    workIn(test()->tenant);

    return ChecklistQuestion::query()->where('checklist_id', ($checklist ?? draft())->id)->where('code', $code)->sole();
}

function qUrl(string $name, mixed ...$extra): string
{
    return route("tenant.telework.checklist.{$name}", [test()->tenant, test()->center->id, ...$extra]);
}

/** @return list<string> */
function codes(?Checklist $checklist = null): array
{
    workIn(test()->tenant);

    return ChecklistQuestion::query()->where('checklist_id', ($checklist ?? draft())->id)->orderBy('position')->pluck('code')->all();
}

it('shows the draft with its questions, coverage and edit rights', function (): void {
    $this->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page
        ->component('telework/checklist')
        ->where('workCenter.name', $this->center->name)
        ->where('checklist.version', 1)
        ->where('checklist.status', 'draft')
        ->where('canEdit', true)
        ->has('questions', 122)
        ->where('questions.0.code', 'S1-01')
        ->where('questions.0.origin', 'dof')
        ->where('missing', ['1' => [], '2' => []]));
});

it('edits only a draft', function (): void {
    $checklist = draft();
    $target = question('S1-01');
    $published = $this->post(qUrl('publish'));
    $published->assertSessionHasNoErrors();

    $this->put(qUrl('questions.update', $target->id), ['text' => 'Otra', 'favorable' => 'no', 'sensitive' => false, 'requirement' => null])->assertSessionHasErrors('checklist');
    $this->delete(qUrl('questions.destroy', $target->id))->assertSessionHasErrors('checklist');
    $this->post(qUrl('questions.move', $target->id), ['direction' => 'down'])->assertSessionHasErrors('checklist');
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'x', 'text' => 'x', 'favorable' => 'yes', 'sensitive' => false])->assertSessionHasErrors('checklist');

    $fresh = question('S1-01', $checklist);
    expect($fresh->text)->toBe($target->text)->and($fresh->origin)->toBe(QuestionOrigin::Dof)->and(codes($checklist))->toHaveCount(122);
});

it('marks an edited DOF question as dof_modified and keeps its base code', function (): void {
    $target = question('S1-01');

    $this->put(qUrl('questions.update', $target->id), ['text' => '¿Texto nuevo?', 'favorable' => $target->favorable->value, 'sensitive' => $target->sensitive, 'requirement' => $target->requirement?->value])->assertSessionHasNoErrors();

    $fresh = question('S1-01');
    expect($fresh->text)->toBe('¿Texto nuevo?')->and($fresh->origin)->toBe(QuestionOrigin::DofModified)->and($fresh->base_code)->toBe('S1-01');
});

it('keeps the origin when only the favorable answer, sensitivity or requirement change', function (): void {
    $target = question('S1-01');

    $this->put(qUrl('questions.update', $target->id), ['text' => $target->text, 'favorable' => 'informative', 'sensitive' => true, 'requirement' => Requirement::Privacy->value])->assertSessionHasNoErrors();

    $fresh = question('S1-01');
    expect($fresh->origin)->toBe(QuestionOrigin::Dof)->and($fresh->favorable)->toBe(Favorable::Informative)
        ->and($fresh->sensitive)->toBeTrue()->and($fresh->requirement)->toBe(Requirement::Privacy);

    $this->put(qUrl('questions.update', $target->id), ['text' => $target->text, 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null])->assertSessionHasNoErrors();
    expect(question('S1-01')->requirement)->toBeNull();
});

it('turns an edited Humetría question into a committee one and keeps an already modified one modified', function (): void {
    workIn($this->tenant);
    $humetria = ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('origin', QuestionOrigin::Humetria)->orderBy('position')->firstOrFail();
    $this->put(qUrl('questions.update', $humetria->id), ['text' => 'Mi pregunta', 'favorable' => $humetria->favorable->value, 'sensitive' => false, 'requirement' => null])->assertSessionHasNoErrors();
    expect(question($humetria->code)->origin)->toBe(QuestionOrigin::Committee)->and(question($humetria->code)->base_code)->toBe($humetria->base_code);

    $dof = question('S1-02');
    $this->put(qUrl('questions.update', $dof->id), ['text' => 'Uno', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null]);
    $this->put(qUrl('questions.update', $dof->id), ['text' => 'Dos', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null]);
    expect(question('S1-02')->origin)->toBe(QuestionOrigin::DofModified)->and(question('S1-02')->text)->toBe('Dos');
});

it('validates the edit', function (array $overrides, string $field): void {
    $target = question('S1-01');

    $this->put(qUrl('questions.update', $target->id), ['text' => 'ok', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null, ...$overrides])->assertSessionHasErrors($field);
})->with([
    'no text' => [['text' => ''], 'text'],
    'bad favorable' => [['favorable' => 'maybe'], 'favorable'],
    'bad requirement' => [['requirement' => '9.9z'], 'requirement'],
    'sensitive not boolean' => [['sensitive' => 'x'], 'sensitive'],
]);

it('adds a committee question with a yes, no or informative favorable at the end of its section', function (string $favorable): void {
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'Mi tema', 'text' => '¿Tiene extintor?', 'favorable' => $favorable, 'sensitive' => false, 'requirement' => '7.1a'])->assertSessionHasNoErrors();
    $this->post(qUrl('questions.store'), ['section' => 2, 'heading' => 'Mi tema', 'text' => '¿Otra?', 'favorable' => 'yes', 'sensitive' => true])->assertSessionHasNoErrors();
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'Mi tema', 'text' => '¿Y otra?', 'favorable' => 'yes', 'sensitive' => false])->assertSessionHasNoErrors();

    $added = question('C1-01');
    $order = codes();
    expect($added->origin)->toBe(QuestionOrigin::Committee)->and($added->base_code)->toBeNull()->and($added->favorable->value)->toBe($favorable)
        ->and($added->requirement)->toBe(Requirement::CleanTidy)->and($added->section)->toBe(1)
        ->and(question('C2-01')->sensitive)->toBeTrue()->and(question('C2-01')->requirement)->toBeNull()
        ->and($order)->toHaveCount(125)
        ->and(array_search('C1-02', $order, true))->toBe(array_search('C1-01', $order, true) + 1)
        ->and(array_search('C1-02', $order, true))->toBeLessThan(array_search('S2-01', $order, true))
        ->and(array_search('C2-01', $order, true))->toBe(124)
        ->and(ChecklistQuestion::query()->where('checklist_id', draft()->id)->orderBy('position')->pluck('position')->all())->toBe(range(1, 125));
})->with(['yes', 'no', 'informative']);

it('validates the new question', function (array $overrides, string $field): void {
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'x', 'text' => 'x', 'favorable' => 'yes', 'sensitive' => false, ...$overrides])->assertSessionHasErrors($field);
    expect(codes())->toHaveCount(122);
})->with([
    'section 3' => [['section' => 3], 'section'],
    'no heading' => [['heading' => ''], 'heading'],
    'no text' => [['text' => ''], 'text'],
    'bad favorable' => [['favorable' => 'x'], 'favorable'],
]);

it('removes a question and closes the gap', function (): void {
    $this->delete(qUrl('questions.destroy', question('S1-02')->id))->assertSessionHasNoErrors();

    $order = codes();
    expect($order)->toHaveCount(121)->and($order)->not->toContain('S1-02')->and($order[1])->toBe('S1-03')
        ->and(ChecklistQuestion::query()->where('checklist_id', draft()->id)->orderBy('position')->pluck('position')->all())->toBe(range(1, 121));
});

it('moves a question up or down inside its section and not past its edges', function (): void {
    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'up'])->assertSessionHasNoErrors();
    expect(array_slice(codes(), 0, 3))->toBe(['S1-02', 'S1-01', 'S1-03']);

    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'up'])->assertSessionHasNoErrors();
    $this->post(qUrl('questions.move', question('S1-01')->id), ['direction' => 'down'])->assertSessionHasNoErrors();
    expect(array_slice(codes(), 0, 3))->toBe(['S1-02', 'S1-03', 'S1-01']);

    // The last of section I stays in section I.
    $last = collect(codes())->filter(fn (string $code): bool => str_starts_with($code, 'S1-') || str_starts_with($code, 'H1-'))->last();
    $this->post(qUrl('questions.move', question($last)->id), ['direction' => 'down'])->assertSessionHasNoErrors();
    expect(array_search($last, codes(), true))->toBeLessThan(array_search('S2-01', codes(), true));

    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    expect(ChecklistQuestion::query()->where('checklist_id', draft()->id)->orderBy('position')->pluck('position')->all())->toBe(range(1, 122));
});

it('warns about each requirement left uncovered per section but still publishes', function (): void {
    workIn($this->tenant);
    $sectionOne = ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('section', 1)->where('requirement', Requirement::FamilyViolenceChannel->value)->get();
    expect($sectionOne)->toHaveCount(1);
    $this->delete(qUrl('questions.destroy', $sectionOne->sole()->id))->assertSessionHasNoErrors();

    expect(resolve(Coverage::class)->missing(draft()))->toBe([1 => [Requirement::FamilyViolenceChannel], 2 => []]);
    $this->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page->where('missing', ['1' => ['7.3c'], '2' => []])->where('missingLabels.1.0', Requirement::FamilyViolenceChannel->label()));

    $this->post(qUrl('publish'))->assertSessionHasNoErrors();
    workIn($this->tenant);
    expect(Checklist::query()->sole()->status)->toBe(ChecklistStatus::Published);
    expect(AuditEntry::query()->where('event', 'telework.checklist_published')->sole()->metadata)->toMatchArray(['version' => 1, 'missing_section_1' => 1, 'missing_section_2' => 0]);
});

it('reports every requirement of a section with no question', function (): void {
    workIn($this->tenant);
    ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('section', 2)->update(['requirement' => null]);

    expect(resolve(Coverage::class)->missing(draft())[2])->toBe(Requirement::cases())->and(resolve(Coverage::class)->missing(draft())[1])->toBe([]);
});

it('publishes as a new version and later drafts copy the latest published', function (): void {
    $first = draft();
    $this->put(qUrl('questions.update', question('S1-01')->id), ['text' => 'Editada', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null]);
    Log::spy();
    $this->post(qUrl('publish'))->assertSessionHasNoErrors();

    workIn($this->tenant);
    $published = Checklist::query()->findOrFail($first->id);
    expect($published->status)->toBe(ChecklistStatus::Published)->and($published->version)->toBe(1)
        ->and($published->published_by)->toBe($this->cora->id)->and($published->published_at)->not->toBeNull()
        ->and(Checklist::latestPublished($this->center->id)?->id)->toBe($first->id);
    Log::shouldHaveReceived('info')->with('telework.checklist_published', Mockery::type('array'));

    $this->post(qUrl('draft'))->assertSessionHasNoErrors();
    $second = draft();
    expect($second->version)->toBe(2)->and(codes($second))->toBe(codes($first))->and(question('S1-01', $second)->text)->toBe('Editada')
        ->and(question('S1-01', $second)->origin)->toBe(QuestionOrigin::DofModified)->and(question('S1-01', $second)->id)->not->toBe(question('S1-01', $first)->id);

    // Editing the new draft leaves the published one alone; asking again returns the same draft.
    $this->put(qUrl('questions.update', question('S1-01', $second)->id), ['text' => 'Tercera', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null]);
    $this->post(qUrl('draft'))->assertSessionHasNoErrors();
    expect(question('S1-01', $first)->text)->toBe('Editada')->and(Checklist::query()->count())->toBe(2)->and(draft()->id)->toBe($second->id);

    $this->post(qUrl('publish'))->assertSessionHasNoErrors();
    $this->post(qUrl('publish'))->assertSessionHasErrors('checklist');
    $this->post(qUrl('draft'));
    expect(draft()->version)->toBe(3);
});

it('shows the latest published version when there is no draft, read only until a new draft starts', function (): void {
    $this->post(qUrl('publish'));

    $this->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page->where('checklist.status', 'published')->where('canStartDraft', true)->has('questions', 122));
});

it('lets only assigned committee members edit', function (): void {
    $target = question('S1-01');
    $body = ['text' => 'Otra', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null];

    // RH is not on the committee: may look, may not touch.
    $this->actingAs($this->hr);
    $this->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
    $this->put(qUrl('questions.update', $target->id), $body)->assertForbidden();
    $this->delete(qUrl('questions.destroy', $target->id))->assertForbidden();
    $this->post(qUrl('questions.move', $target->id), ['direction' => 'down'])->assertForbidden();
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'x', 'text' => 'x', 'favorable' => 'yes', 'sensitive' => false])->assertForbidden();
    $this->post(qUrl('publish'))->assertForbidden();
    $this->post(qUrl('draft'))->assertForbidden();

    // A committee user of another center and a viewer do not even see it.
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    App\Modules\Telework\Models\CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'user_id' => $elsewhere->id]);
    foreach ([$elsewhere, memberOf($this->tenant, TenantRole::Viewer)] as $user) {
        $this->actingAs($user);
        $this->get(qUrl('show'))->assertNotFound();
        $this->put(qUrl('questions.update', $target->id), $body)->assertNotFound();
        $this->post(qUrl('publish'))->assertNotFound();
    }

    expect(question('S1-01')->text)->toBe($target->text);
});

it('tells who may not edit that the checklist is not theirs before it judges what they sent', function (): void {
    $target = question('S1-01');
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $elsewhere = memberOf($this->tenant, TenantRole::Committee);
    App\Modules\Telework\Models\CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'user_id' => $elsewhere->id]);
    $send = function () use ($target): array {
        return [
            $this->put(qUrl('questions.update', $target->id), [])->status(),
            $this->post(qUrl('questions.store'), [])->status(),
            $this->post(qUrl('questions.move', $target->id), ['direction' => 'sideways'])->status(),
        ];
    };

    $this->actingAs($this->hr);
    expect($send())->toBe([403, 403, 403]);
    $this->actingAs($elsewhere);
    expect($send())->toBe([404, 404, 404]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    expect($send())->toBe([404, 404, 404]);
    $this->put(route('tenant.telework.checklist.questions.update', [$this->tenant, 'nope', $target->id]), [])->assertNotFound();

    // The member who may edit is told what is wrong.
    $this->actingAs($this->cora);
    $this->post(qUrl('questions.move', $target->id), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
});

it('does not let a company reach the checklist of another', function (): void {
    $intruder = Tenant::factory()->create();
    $owner = memberOf($intruder, TenantRole::Owner);
    $target = question('S1-01');

    $this->actingAs($owner)->get(route('tenant.telework.checklist.show', [$intruder, $this->center->id]))->assertNotFound();
    $this->put(route('tenant.telework.checklist.questions.update', [$intruder, $this->center->id, $target->id]), ['text' => 'x', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null])->assertNotFound();
    $this->post(route('tenant.telework.checklist.publish', [$intruder, $this->center->id]))->assertNotFound();
});

it('answers 404 for an unknown center, a question of another center or a malformed id', function (): void {
    $this->get(route('tenant.telework.checklist.show', [$this->tenant, 'nope']))->assertNotFound();
    $this->get(route('tenant.telework.checklist.show', [$this->tenant, fake()->uuid()]))->assertNotFound();
    $this->delete(qUrl('questions.destroy', 'nope'))->assertNotFound();
    $this->delete(qUrl('questions.destroy', fake()->uuid()))->assertNotFound();
});

it('writes a log line without the question texts when editing', function (): void {
    Log::spy();
    $this->put(qUrl('questions.update', question('S1-01')->id), ['text' => 'Texto secreto', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null]);

    Log::shouldHaveReceived('info')->with('telework.question_updated', Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Texto secreto')));
});

it('answers 404 for a question of another center of the same company, and leaves it untouched', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    $this->actingAs($this->hr)->post(route('tenant.telework.committee.store', [$this->tenant, $other->id]), ['constituted_on' => '2026-09-15', 'member_ids' => [$stranger->id]])->assertSessionHasNoErrors();
    workIn($this->tenant);
    $theirs = Checklist::query()->where('work_center_id', $other->id)->sole();
    $foreign = ChecklistQuestion::query()->where('checklist_id', $theirs->id)->where('code', 'S1-01')->sole();
    $before = $foreign->only(['text', 'position', 'origin']);
    $this->actingAs($this->cora);

    $this->put(qUrl('questions.update', $foreign->id), ['text' => 'Intrusa', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null])->assertNotFound();
    $this->delete(qUrl('questions.destroy', $foreign->id))->assertNotFound();
    $this->post(qUrl('questions.move', $foreign->id), ['direction' => 'down'])->assertNotFound();

    workIn($this->tenant);
    expect(ChecklistQuestion::query()->findOrFail($foreign->id)->only(['text', 'position', 'origin']))->toBe($before)
        ->and(ChecklistQuestion::query()->where('checklist_id', $theirs->id)->count())->toBe(122);
});

it('never reuses a Comisión code of the center', function (): void {
    $body = ['section' => 1, 'heading' => 'Mi tema', 'text' => '¿Algo?', 'favorable' => 'yes', 'sensitive' => false];

    $this->post(qUrl('questions.store'), $body);
    $this->post(qUrl('questions.store'), $body);
    $this->post(qUrl('questions.store'), $body);

    // Across versions: a code that only an older version carries is not free either.
    $first = draft();
    $this->post(qUrl('publish'))->assertSessionHasNoErrors();
    $this->post(qUrl('draft'));
    $this->delete(qUrl('questions.destroy', question('C1-03')->id));
    $this->post(qUrl('questions.store'), $body);
    expect(codes())->toContain('C1-04')->and(codes())->not->toContain('C1-03')->and(codes($first))->toContain('C1-03');
});

it('renumbers whatever the positions were, even near the column limit', function (): void {
    workIn($this->tenant);
    ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('code', 'S2-01')->update(['position' => 32000]);

    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'up'])->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(ChecklistQuestion::query()->where('checklist_id', draft()->id)->orderBy('position')->pluck('position')->all())->toBe(range(1, 122));
});

it('returns, with the published version, the gaps it audited', function (): void {
    workIn($this->tenant);
    ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('section', 2)->where('requirement', Requirement::Noise->value)->update(['requirement' => null]);

    $result = resolve(PublishChecklist::class)->handle(draft()->id, $this->cora->id);

    expect($result['checklist']->status)->toBe(ChecklistStatus::Published)->and($result['missing'])->toBe([1 => [], 2 => [Requirement::Noise]]);
});

it('locks the published version while starting a draft, so two starts at once cannot collide', function (): void {
    $this->post(qUrl('publish'))->assertSessionHasNoErrors();
    workIn($this->tenant);
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    resolve(StartChecklistDraft::class)->handle($this->center->id);

    $locks = array_filter($statements, fn (string $sql): bool => str_contains($sql, 'from "checklists"') && str_contains($sql, 'for update'));
    expect($locks)->toHaveCount(2)->and(array_values(array_filter($locks, fn (string $sql): bool => str_contains($sql, 'order by "version" desc'))))->toHaveCount(1);
});

it('sends each question with every field, the catalog of requirements and the center', function (): void {
    $first = question('S1-01');

    $this->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page
        ->where('workCenter', ['id' => $this->center->id, 'name' => $this->center->name])
        ->where('canStartDraft', false)
        ->where('questions.0', [
            'id' => $first->id,
            'section' => 1,
            'code' => 'S1-01',
            'heading' => $first->heading,
            'text' => $first->text,
            'origin' => 'dof',
            'favorable' => $first->favorable->value,
            'requirement' => $first->requirement?->value,
            'sensitive' => $first->sensitive,
        ])
        ->where('requirements', array_map(fn (Requirement $requirement): array => ['value' => $requirement->value, 'label' => $requirement->label()], Requirement::cases())));
});

it('lets RH read the published list without offering to start a draft', function (): void {
    $this->post(qUrl('publish'))->assertSessionHasNoErrors();

    $this->actingAs($this->hr)->get(qUrl('show'))->assertInertia(fn (Assert $page) => $page
        ->where('checklist.status', 'published')
        ->where('canEdit', false)
        ->where('canStartDraft', false));
});

it('answers 404 for a center whose Comisión has no list yet', function (): void {
    $empty = WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs($this->hr)->get(route('tenant.telework.checklist.show', [$this->tenant, $empty->id]))->assertNotFound();
});

it('confirms each change with a toast and comes back to the list', function (): void {
    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'Mi tema', 'text' => '¿Tiene extintor?', 'favorable' => 'yes', 'sensitive' => false])
        ->assertRedirect(qUrl('show'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Pregunta agregada.']]);
    $this->put(qUrl('questions.update', question('C1-01')->id), ['text' => 'Otra', 'favorable' => 'yes', 'sensitive' => false, 'requirement' => null])
        ->assertRedirect(qUrl('show'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Pregunta guardada.']]);
    $this->post(qUrl('questions.move', question('C1-01')->id), ['direction' => 'up'])->assertRedirect(qUrl('show'));
    $this->delete(qUrl('questions.destroy', question('C1-01')->id))
        ->assertRedirect(qUrl('show'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Pregunta quitada del borrador.']]);
    $this->post(qUrl('publish'))
        ->assertRedirect(qUrl('show'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Versión 1 publicada.']]);
    $this->post(qUrl('draft'))
        ->assertRedirect(qUrl('show'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Borrador nuevo listo, copiado de la última versión publicada.']]);
});

it('warns on publishing which requirements of each section have no question, section I first', function (): void {
    workIn($this->tenant);
    ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('section', 1)->where('requirement', Requirement::FamilyViolenceChannel->value)->update(['requirement' => null]);
    ChecklistQuestion::query()->where('checklist_id', draft()->id)->where('section', 2)->whereIn('requirement', [Requirement::Chair->value, Requirement::Noise->value])->update(['requirement' => null]);
    $sectionTwo = array_values(array_filter(Requirement::cases(), fn (Requirement $requirement): bool => in_array($requirement, [Requirement::Chair, Requirement::Noise], true)));

    $this->post(qUrl('publish'))->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => [
        'type' => 'warning',
        'message' => 'Versión 1 publicada. Sin pregunta para: '.implode('; ', array_map(fn (Requirement $requirement): string => $requirement->label(), [Requirement::FamilyViolenceChannel, ...$sectionTwo])).'.',
    ]]);
});

it('copies every field of each question into the new draft, with its own times, and logs the draft with ids', function (): void {
    $this->travelTo('2026-10-01 12:00:00');
    workIn($this->tenant);
    $source = question('S1-01');
    $source->update(['requirement' => Requirement::Chair, 'sensitive' => true]);
    $this->post(qUrl('publish'))->assertSessionHasNoErrors();
    Log::spy();

    $this->post(qUrl('draft'))->assertSessionHasNoErrors();

    $copy = question('S1-01', draft());
    expect($copy->requirement)->toBe(Requirement::Chair)
        ->and($copy->sensitive)->toBeTrue()
        ->and($copy->base_code)->toBe($source->base_code)
        ->and($copy->created_at?->equalTo(now()))->toBeTrue()
        ->and($copy->updated_at?->equalTo(now()))->toBeTrue();
    Log::shouldHaveReceived('info')->with('telework.checklist_draft_started', ['work_center_id' => $this->center->id, 'checklist_id' => draft()->id, 'version' => 2])->once();
});

it('says there is no published list to copy', function (): void {
    $empty = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);

    expect(fn () => resolve(StartChecklistDraft::class)->handle($empty->id))->toThrow(Illuminate\Validation\ValidationException::class, 'Este centro aún no tiene una lista publicada que copiar.');
});

it('leaves the first question up and the last one down where they are, and logs each move with its direction', function (): void {
    $order = codes();
    Log::spy();

    $this->post(qUrl('questions.move', question($order[0])->id), ['direction' => 'up'])->assertRedirect(qUrl('show'));
    $this->post(qUrl('questions.move', question($order[121])->id), ['direction' => 'down'])->assertRedirect(qUrl('show'));
    expect(codes())->toBe($order);
    Log::shouldNotHaveReceived('info', fn (string $message): bool => $message === 'telework.question_moved');

    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'up']);
    $this->post(qUrl('questions.move', question('S1-02')->id), ['direction' => 'down']);
    Log::shouldHaveReceived('info')->with('telework.question_moved', ['checklist_id' => draft()->id, 'question_id' => question('S1-02')->id, 'direction' => 'up'])->once();
    Log::shouldHaveReceived('info')->with('telework.question_moved', ['checklist_id' => draft()->id, 'question_id' => question('S1-02')->id, 'direction' => 'down'])->once();
});

it('logs each added question with its code', function (): void {
    Log::spy();

    $this->post(qUrl('questions.store'), ['section' => 1, 'heading' => 'Mi tema', 'text' => '¿Tiene extintor?', 'favorable' => 'yes', 'sensitive' => false]);

    Log::shouldHaveReceived('info')->with('telework.question_added', ['checklist_id' => draft()->id, 'question_id' => question('C1-01')->id, 'code' => 'C1-01'])->once();
});
