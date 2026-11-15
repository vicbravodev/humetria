<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\Professional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * HR adds or edits an action of the Programa. The shape is checked here; the rules of the norm (the third level, the areas
 * shown, the dates) are the action's.
 */
final class ActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-interventions') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ActionKind::class)],
            'level' => ['required', 'integer', Rule::enum(ActionLevel::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'subjects' => ['required', Rule::enum(ActionSubjects::class)],
            'area_ids' => ['nullable', 'array', 'max:100'],
            'area_ids.*' => ['uuid'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'responsible_name' => ['nullable', 'string', 'max:120'],
            'responsible_user_id' => ['nullable', 'integer'],
            'professional' => ['nullable', Rule::enum(Professional::class)],
        ];
    }

    /**
     * The screen calls it «fecha límite» (the word of the column and the form), so the error does too.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['due_on' => 'fecha límite'];
    }

    public function toData(?string $id, ?string $itemId): ActionData
    {
        return new ActionData(
            id: $id,
            itemId: $itemId,
            kind: ActionKind::from($this->string('kind')->toString()),
            level: ActionLevel::from($this->integer('level')),
            title: $this->string('title')->toString(),
            description: $this->string('description')->toString(),
            subjects: ActionSubjects::from($this->string('subjects')->toString()),
            areaIds: array_values(array_filter($this->array('area_ids'), is_string(...))),
            startsOn: $this->filled('starts_on') ? $this->string('starts_on')->toString() : null,
            dueOn: $this->string('due_on')->toString(),
            responsibleName: $this->filled('responsible_name') ? $this->string('responsible_name')->toString() : null,
            responsibleUserId: $this->filled('responsible_user_id') ? $this->integer('responsible_user_id') : null,
            professional: $this->filled('professional') ? Professional::from($this->string('professional')->toString()) : null,
        );
    }
}
