<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Actions\AddQuestion;
use App\Modules\Telework\Actions\MoveQuestion;
use App\Modules\Telework\Actions\PublishChecklist;
use App\Modules\Telework\Actions\RemoveQuestion;
use App\Modules\Telework\Actions\StartChecklistDraft;
use App\Modules\Telework\Actions\UpdateQuestion;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Http\Requests\MoveQuestionRequest;
use App\Modules\Telework\Http\Requests\QuestionRequest;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistAccess;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Telework\Support\Coverage;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The checklist of one work center. RH and the Comisión can read it; only an assigned Comisión member edits or publishes.
 * Someone who can neither manage Telework nor sit on that center's Comisión gets a 404; RH not on it, a 403.
 */
final class ChecklistController extends Controller
{
    public function __construct(private readonly ChecklistAccess $access) {}

    public function show(Request $request, mixed $tenant, string $workCenter, CommitteeGate $gate, Coverage $coverage): Response
    {
        $center = $this->visibleCenter($request, $workCenter);
        $draft = Checklist::query()->where('work_center_id', $center->id)->where('status', ChecklistStatus::Draft)->first();
        $checklist = $draft ?? Checklist::latestPublished($center->id);
        abort_if($checklist === null, 404);
        $member = $gate->isMember($this->userId($request), $center->id);
        $missing = $coverage->missing($checklist);

        return Inertia::render('telework/checklist', [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'checklist' => ['version' => $checklist->version, 'status' => $checklist->status->value],
            'canEdit' => $member && $draft !== null,
            'canStartDraft' => $member && $draft === null,
            'questions' => $checklist->questions()->get()->map(fn (ChecklistQuestion $question): array => [
                'id' => $question->id,
                'section' => $question->section,
                'code' => $question->code,
                'heading' => $question->heading,
                'text' => $question->text,
                'origin' => $question->origin->value,
                'favorable' => $question->favorable->value,
                'requirement' => $question->requirement?->value,
                'sensitive' => $question->sensitive,
            ])->all(),
            'requirements' => array_map(fn (Requirement $requirement): array => ['value' => $requirement->value, 'label' => $requirement->label()], Requirement::cases()),
            // A backed enum reaches the browser as its value: sending the cases themselves would send the same JSON.
            'missing' => array_map(fn (array $list): array => array_map(fn (Requirement $requirement): string => $requirement->value, $list), $missing), // @pest-mutate-ignore: UnwrapArrayMap
            'missingLabels' => array_map(fn (array $list): array => array_map(fn (Requirement $requirement): string => $requirement->label(), $list), $missing),
        ]);
    }

    public function store(QuestionRequest $request, mixed $tenant, string $workCenter, AddQuestion $add): RedirectResponse
    {
        $draft = $this->openDraft($request, $workCenter);

        $add->handle($draft->id, $request->section(), $request->heading(), $request->questionText(), $request->favorable(), $request->requirement(), $request->sensitive());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pregunta agregada.']);

        return $this->back($tenant, $workCenter);
    }

    public function update(QuestionRequest $request, mixed $tenant, string $workCenter, string $question, UpdateQuestion $update): RedirectResponse
    {
        $found = $this->question($request, $workCenter, $question);

        $update->handle($found, $request->questionText(), $request->favorable(), $request->requirement(), $request->sensitive());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pregunta guardada.']);

        return $this->back($tenant, $workCenter);
    }

    public function destroy(Request $request, mixed $tenant, string $workCenter, string $question, RemoveQuestion $remove): RedirectResponse
    {
        $remove->handle($this->question($request, $workCenter, $question));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pregunta quitada del borrador.']);

        return $this->back($tenant, $workCenter);
    }

    public function move(MoveQuestionRequest $request, mixed $tenant, string $workCenter, string $question, MoveQuestion $move): RedirectResponse
    {
        $found = $this->question($request, $workCenter, $question);

        $move->handle($found, $request->up());

        return $this->back($tenant, $workCenter);
    }

    public function draft(Request $request, mixed $tenant, string $workCenter, StartChecklistDraft $start): RedirectResponse
    {
        $center = $this->editableCenter($request, $workCenter);

        $start->handle($center->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador nuevo listo, copiado de la última versión publicada.']);

        return $this->back($tenant, $workCenter);
    }

    public function publish(Request $request, mixed $tenant, string $workCenter, PublishChecklist $publish): RedirectResponse
    {
        $draft = $this->draftOf($this->editableCenter($request, $workCenter));

        ['checklist' => $published, 'missing' => $missing] = $publish->handle($draft->id, $this->userId($request));
        $gaps = array_map(fn (Requirement $requirement): string => $requirement->label(), [...$missing[1], ...$missing[2]]);
        Inertia::flash('toast', [
            'type' => $gaps === [] ? 'success' : 'warning',
            'message' => "Versión {$published->version} publicada.".($gaps === [] ? '' : ' Sin pregunta para: '.implode('; ', $gaps).'.'),
        ]);

        return $this->back($tenant, $workCenter);
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        // Every route of this controller sits behind the auth middleware: the check only narrows the type.
        abort_if($user === null, 403); // @pest-mutate-ignore: DecrementInteger,IncrementInteger,RemoveFunctionCall

        return $user->id;
    }

    private function back(mixed $tenant, string $workCenter): RedirectResponse
    {
        return redirect()->route('tenant.telework.checklist.show', [$tenant, $workCenter]);
    }

    /** A center the user may look at: RH or an assigned Comisión member. */
    private function visibleCenter(Request $request, string $id): WorkCenterSummary
    {
        return $this->access->visible($this->userId($request), $id);
    }

    private function editableCenter(Request $request, string $id): WorkCenterSummary
    {
        return $this->access->editable($this->userId($request), $id);
    }

    private function draftOf(WorkCenterSummary $center): Checklist
    {
        return Checklist::query()->where('work_center_id', $center->id)->where('status', ChecklistStatus::Draft)->first()
            ?? throw ValidationException::withMessages(['checklist' => 'Este centro no tiene un borrador abierto: inicia uno nuevo para cambiar la lista.']);
    }

    private function openDraft(Request $request, string $id): Checklist
    {
        return $this->draftOf($this->editableCenter($request, $id));
    }

    /** A question of this center's checklist (draft or published; the action refuses a published one). */
    private function question(Request $request, string $workCenter, string $id): ChecklistQuestion
    {
        $center = $this->editableCenter($request, $workCenter);
        $question = Str::isUuid($id)
            ? ChecklistQuestion::query()->whereHas('checklist', fn (Builder $checklists) => $checklists->where('work_center_id', $center->id))->find($id)
            : null;
        abort_if($question === null, 404);

        return $question;
    }
}
