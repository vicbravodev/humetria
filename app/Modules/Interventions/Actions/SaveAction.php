<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR adds an action to a row of the Programa or edits one (num. 8.4 and 8.5). It is for the whole work center or for areas
 * Results shows (criterio C-33): an area that is hidden, unknown or another company's is refused with one message, so it
 * reveals nothing. The third level is clinical care and names the professional who gives it (criterio C-32). A cancelled
 * action is not edited (criterio C-35). What HR wrote never reaches the log.
 */
final readonly class SaveAction
{
    public function __construct(private CurrentTenant $tenant, private Members $members, private CampaignRecords $campaigns, private RiskResults $results) {}

    /**
     * @throws ValidationException
     * @throws ModelNotFoundException when the row or the action is not of this company.
     */
    public function handle(ActionData $data, int $userId): ProgramAction
    {
        $existing = $data->id === null ? null : ProgramAction::query()->findOrFail($data->id);
        $item = ProgramItem::query()->findOrFail($existing->item_id ?? $data->itemId ?? throw new ModelNotFoundException);
        $program = Program::query()->findOrFail($item->program_id);

        if ($existing?->status === ActionStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Esta acción está cancelada y no se edita.']);
        }

        [$responsibleName, $responsibleUserId, $responsibleErrors] = $this->responsible($data, $program);
        $errors = [...$this->levelErrors($data), ...$this->dateErrors($data), ...$this->areaErrors($data, $program), ...$responsibleErrors];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($data, $existing, $item, $program, $userId, $responsibleName, $responsibleUserId): ProgramAction {
            $action = $existing ?? new ProgramAction;

            if ($existing === null) {
                $action->fill(['tenant_id' => $this->tenant->id(), 'program_id' => $program->id, 'item_id' => $item->id, 'status' => ActionStatus::Planned, 'created_by' => $userId]);
            }

            $action->fill([
                'level' => $data->level,
                'kind' => $data->kind,
                'title' => trim($data->title),
                'description' => trim($data->description),
                'subjects' => $data->subjects,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'responsible_name' => $responsibleName,
                'responsible_user_id' => $responsibleUserId,
                // Only the third level names a professional.
                'professional' => $data->level === ActionLevel::Individual ? $data->professional : null,
            ])->save();

            $action->areas()->delete();

            if ($data->subjects === ActionSubjects::Areas) {
                foreach (array_unique($data->areaIds) as $areaId) {
                    $action->areas()->create(['tenant_id' => $action->tenant_id, 'area_id' => $areaId]);
                }
            }

            Log::info('intervention.action_saved', [
                'action_id' => $action->id,
                'program_id' => $program->id,
                'item_id' => $item->id,
                'level' => $data->level->value,
                'kind' => $data->kind->value,
                'subjects' => $data->subjects->value,
                'created' => $existing === null,
            ]);

            return $action;
        });
    }

    /**
     * @return array<string, string>
     */
    private function levelErrors(ActionData $data): array
    {
        $clinical = $data->kind === ActionKind::ClinicalCare;
        $third = $data->level === ActionLevel::Individual;

        return match (true) {
            $clinical && ! $third => ['level' => 'La atención clínica o terapéutica es de tercer nivel.'],
            $third && ! $clinical => ['level' => 'El tercer nivel es la atención clínica o terapéutica.'],
            $third && $data->professional === null => ['professional' => 'Indica si la da un médico, un psicólogo o un psiquiatra.'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function dateErrors(ActionData $data): array
    {
        return $data->startsOn !== null && $data->dueOn < $data->startsOn ? ['due_on' => 'La fecha límite no puede ser anterior al inicio.'] : [];
    }

    /**
     * The areas must be among those Results shows (minimum and complementary suppression applied). Hidden, unknown and
     * another company's are the same to this check, so the message says the same for all.
     *
     * @return array<string, string>
     */
    private function areaErrors(ActionData $data, Program $program): array
    {
        if ($data->subjects !== ActionSubjects::Areas) {
            return [];
        }

        $campaign = $this->campaigns->find($program->campaign_id);
        $shown = $campaign === null ? [] : array_map(fn ($area): string => $area->id, $this->results->areaGroups($campaign));

        return $data->areaIds === [] || array_diff($data->areaIds, $shown) !== [] ? ['area_ids' => 'Elige áreas de la lista.'] : [];
    }

    /**
     * Who answers for the action: a member of the company (by their own name), a name, or the Programa's responsible.
     *
     * @return array{string|null, int|null, array<string, string>}
     */
    private function responsible(ActionData $data, Program $program): array
    {
        if ($data->responsibleUserId !== null) {
            $member = $this->members->find($data->responsibleUserId);

            return $member === null ? [null, null, ['responsible_user_id' => 'Esa persona no pertenece a la empresa.']] : [$member->name, $member->userId, []];
        }

        $name = trim($data->responsibleName ?? '');

        if ($name !== '') {
            return [$name, null, []];
        }

        return $program->responsible_name === null
            ? [null, null, ['responsible_name' => 'Indica quién es responsable de la acción.']]
            : [$program->responsible_name, $program->responsible_user_id, []];
    }
}
