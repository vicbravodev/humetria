<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Interventions\Actions\AddManualItem;
use App\Modules\Interventions\Actions\CopyAction;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\RecordFollowUp;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Actions\SaveAction;
use App\Modules\Interventions\Actions\SaveAnalysis;
use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Http\Controllers\ProgramController;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\GuideCatalog;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Context;

mutates(SaveAction::class, RecordProgress::class, AddManualItem::class, CopyAction::class, SaveAnalysis::class, RecordFollowUp::class, ProgramController::class);

/**
 * The bitácora of the Programa (9b): every change goes to `audit_entries` with who made it (and who impersonated them), and
 * a change of an action keeps what its due day, responsible and words were. Opening the page is audited once per person
 * and Programa in a short window, so the trail holds the changes, not the reloads.
 */
beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    Context::add('user_id', $this->hr->id);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->items = ProgramItem::query()->where('program_id', $this->program?->id)->orderBy('scope_key')->get();
});

/** @param  array<string, mixed>  $overrides */
function auditedAction(object $test, array $overrides = []): ActionData
{
    return new ActionData(...[
        'id' => null,
        'itemId' => $test->items[0]->id,
        'kind' => ActionKind::PolicyReview,
        'level' => ActionLevel::Organizational,
        'title' => 'Revisar la política',
        'description' => 'Revisar y difundir la política.',
        'subjects' => ActionSubjects::Center,
        'areaIds' => [],
        'startsOn' => null,
        'dueOn' => '2026-12-15',
        'responsibleName' => 'Laura Méndez',
        'responsibleUserId' => null,
        'professional' => null,
        ...$overrides,
    ]);
}

/**
 * The last entry of `$event`. Its metadata comes back from jsonb with the keys in another order: compare it with toEqual.
 *
 * @return array<string, mixed>
 */
function lastAudit(string $event): array
{
    $entry = AuditEntry::query()->where('event', $event)->latest('id')->firstOrFail();

    return ['actor' => $entry->actor_id, 'impersonator' => $entry->impersonator_id, 'metadata' => $entry->metadata];
}

it('audits a new action, with who added it', function (): void {
    $action = resolve(SaveAction::class)->handle(auditedAction($this), $this->hr->id);

    expect(lastAudit('interventions.action_saved'))->toEqual(['actor' => $this->hr->id, 'impersonator' => null, 'metadata' => ['action_id' => $action->id, 'program_id' => $this->program?->id, 'created' => true, 'changes' => []]]);
});

it('audits an edit with what its due day, responsible and words were and are, and only what changed', function (): void {
    $action = resolve(SaveAction::class)->handle(auditedAction($this), $this->hr->id);
    $member = memberOf($this->tenant, TenantRole::Hr);

    resolve(SaveAction::class)->handle(auditedAction($this, ['id' => $action->id, 'dueOn' => '2027-01-31', 'startsOn' => '2026-12-01', 'title' => 'Revisar la política de prevención', 'responsibleName' => null, 'responsibleUserId' => $member->id]), $this->hr->id);

    expect(lastAudit('interventions.action_saved')['metadata'])->toEqual(['action_id' => $action->id, 'program_id' => $this->program?->id, 'created' => false, 'changes' => [
        'title' => ['from' => 'Revisar la política', 'to' => 'Revisar la política de prevención'],
        'starts_on' => ['from' => null, 'to' => '2026-12-01'],
        'due_on' => ['from' => '2026-12-15', 'to' => '2027-01-31'],
        'responsible_name' => ['from' => 'Laura Méndez', 'to' => $member->name],
        'responsible_user_id' => ['from' => null, 'to' => $member->id],
    ]]);
});

it('keeps the earlier due day of an overdue action that is moved, so editing it leaves a trace', function (): void {
    $action = resolve(SaveAction::class)->handle(auditedAction($this, ['dueOn' => '2026-11-01']), $this->hr->id);

    resolve(SaveAction::class)->handle(auditedAction($this, ['id' => $action->id, 'dueOn' => '2026-12-31']), $this->hr->id);

    expect(lastAudit('interventions.action_saved')['metadata']['changes'])->toEqual(['due_on' => ['from' => '2026-11-01', 'to' => '2026-12-31']]);
});

it('audits a step of an action, with the impersonator when support records it for the owner', function (): void {
    $action = resolve(SaveAction::class)->handle(auditedAction($this), $this->hr->id);
    $support = User::factory()->create();
    Context::add('impersonator_id', $support->id);

    $entry = resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-20', 'Se convocó al comité.', $this->hr->id);

    expect(lastAudit('interventions.progress_recorded'))->toEqual(['actor' => $this->hr->id, 'impersonator' => $support->id, 'metadata' => [
        'action_id' => $action->id, 'program_id' => $this->program?->id, 'entry_id' => $entry->id, 'from' => 'planned', 'to' => 'in_progress', 'recorded_on' => '2026-11-20', 'with_evidence' => false,
    ]]);
});

it('audits a row added by hand, a copy, an analysis and a follow-up evaluation', function (): void {
    $guide = array_find($this->campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');
    $free = array_find(resolve(GuideCatalog::class)->groups($guide), fn (array $group): bool => ! $this->items->contains(fn (ProgramItem $item): bool => $item->scope === $group['scope'] && $item->scope_key === $group['key']));
    $row = resolve(AddManualItem::class)->handle($this->program?->id ?? '', $free['scope'] ?? ItemScope::Domain, $free['key'] ?? '');
    $action = resolve(SaveAction::class)->handle(auditedAction($this), $this->hr->id);
    $copies = resolve(CopyAction::class)->handle($action->id, [$this->items[1]->id], $this->hr->id);
    resolve(SaveAnalysis::class)->handle($this->items[0]->id, 'Se concentra en la carga de trabajo.');
    resolve(RecordFollowUp::class)->handle($this->items[0]->id, '2027-02-01', null, null, null);
    resolve(RecordFollowUp::class)->handle($this->items[0]->id, '2027-02-01', '2026-11-19', 'Bajó el riesgo.', null);

    expect(lastAudit('interventions.item_added')['metadata'])->toEqual(['item_id' => $row->id, 'program_id' => $this->program?->id, 'scope' => $row->scope->value])
        ->and(lastAudit('interventions.action_copied')['metadata'])->toEqual(['action_id' => $action->id, 'program_id' => $this->program?->id, 'copies' => [$copies[0]->id]])
        ->and(lastAudit('interventions.analysis_saved')['metadata'])->toEqual(['item_id' => $this->items[0]->id, 'program_id' => $this->program?->id, 'written' => true])
        ->and(lastAudit('interventions.follow_up_recorded')['metadata'])->toEqual(['item_id' => $this->items[0]->id, 'program_id' => $this->program?->id, 'changes' => [
            'follow_up_done_on' => ['from' => null, 'to' => '2026-11-19'],
        ]])
        ->and(AuditEntry::query()->where('event', 'interventions.follow_up_recorded')->count())->toBe(2)
        // The copy is an action of its own: it is audited as added, too.
        ->and(AuditEntry::query()->where('event', 'interventions.action_saved')->count())->toBe(2);
});

it('keeps the words of the analysis and of the follow-up result out of the trail', function (): void {
    resolve(SaveAnalysis::class)->handle($this->items[0]->id, 'Texto que escribió RH.');
    resolve(RecordFollowUp::class)->handle($this->items[0]->id, null, '2026-11-19', 'Resultado que escribió RH.', null);

    expect(json_encode(AuditEntry::query()->pluck('metadata')->all(), JSON_UNESCAPED_UNICODE))->not->toContain('escribió RH');
});

it('audits opening the Programa once per person in a window of 30 minutes, not on every reload', function (): void {
    $url = route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $views = fn (): int => AuditEntry::query()->where('event', 'interventions.program_viewed')->count();

    $this->actingAs($this->hr)->get($url)->assertOk();
    $this->actingAs($this->hr)->get($url)->assertOk();
    $this->travel(29)->minutes();
    $this->actingAs($this->hr)->get($url)->assertOk();
    expect($views())->toBe(1);

    $this->actingAs($viewer)->get($url)->assertOk();
    expect($views())->toBe(2);

    $this->travel(2)->minutes();
    $this->actingAs($this->hr)->get($url)->assertOk();
    expect($views())->toBe(3);
});
