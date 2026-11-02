<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Data\PositionData;
use App\Modules\Seating\Enums\BipedestationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A standing position: what the person does standing, the three answers of Figura 1, the conditions of the area and the
 * defaults the assessment of each person starts from. The result of Figura 1 is never read from here.
 */
final class PositionRequest extends FormRequest
{
    private const array AGENTS = ['noise', 'lighting', 'ventilation', 'temperature', 'vibration', 'chemicals'];

    public function authorize(): bool
    {
        return $this->user()?->can('manage-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $condition = ['nullable', 'integer', 'between:1,3'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'tasks' => ['required', 'string', 'max:2000'],
            'bipedestation_type' => ['required', Rule::enum(BipedestationType::class)],
            'continuous_minutes' => ['required', 'integer', 'between:0,1440'],
            'can_work_seated' => ['required', 'boolean'],
            'has_space_for_seat' => ['required', 'boolean'],
            'needs_to_be_nearby' => ['required', 'boolean'],
            'max_people_at_once' => ['required', 'integer', 'between:1,1000'],
            'area_conditions' => ['nullable', 'array'],
            'area_conditions.*' => ['nullable', 'string', 'max:200'],
            'tools' => ['nullable', 'string', 'max:1000'],
            'physical_load' => ['nullable', 'string', 'max:1000'],
            'attention_level' => ['nullable', 'string', 'max:500'],
            'default_standing_time' => $condition,
            'default_posture_change' => $condition,
            'default_surface' => $condition,
            'default_movement_space' => $condition,
            'default_pauses' => $condition,
            'nature_allows' => ['nullable', 'boolean'],
            'nature_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(?string $id, string $workCenterId): PositionData
    {
        $conditions = [];
        foreach (self::AGENTS as $agent) {
            $text = $this->input("area_conditions.{$agent}");
            $conditions[$agent] = is_string($text) && trim($text) !== '' ? trim($text) : null;
        }

        return new PositionData(
            id: $id,
            workCenterId: $workCenterId,
            name: trim($this->string('name')->toString()),
            tasks: trim($this->string('tasks')->toString()),
            type: BipedestationType::from($this->string('bipedestation_type')->toString()),
            continuousMinutes: $this->integer('continuous_minutes'),
            canWorkSeated: $this->boolean('can_work_seated'),
            hasSpaceForSeat: $this->boolean('has_space_for_seat'),
            needsToBeNearby: $this->boolean('needs_to_be_nearby'),
            maxPeopleAtOnce: $this->integer('max_people_at_once'),
            areaConditions: $conditions,
            tools: $this->text('tools'),
            physicalLoad: $this->text('physical_load'),
            attentionLevel: $this->text('attention_level'),
            defaultStandingTime: $this->condition('default_standing_time'),
            defaultPostureChange: $this->condition('default_posture_change'),
            defaultSurface: $this->condition('default_surface'),
            defaultMovementSpace: $this->condition('default_movement_space'),
            defaultPauses: $this->condition('default_pauses'),
            natureAllows: $this->filled('nature_allows') ? $this->boolean('nature_allows') : null,
            natureReason: $this->text('nature_reason'),
        );
    }

    private function text(string $key): ?string
    {
        $text = trim($this->string($key)->toString());

        return $text === '' ? null : $text;
    }

    private function condition(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
