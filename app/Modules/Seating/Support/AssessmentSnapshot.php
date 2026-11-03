<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * The facts of Quinta C of an assessment (criterio C-07): the center, the area, the tools, the load, the attention, the
 * person, the position and the type of bipedestación. Read live while the assessment is a draft, and frozen at the
 * signature: a later change of the position or the center never rewrites what the Comisión signed.
 */
final readonly class AssessmentSnapshot
{
    private const array AGENTS = ['noise' => 'Ruido', 'lighting' => 'Iluminación', 'ventilation' => 'Ventilación', 'temperature' => 'Temperaturas', 'vibration' => 'Vibraciones', 'chemicals' => 'Contaminación química'];

    public function __construct(private WorkCenters $workCenters, private Roster $roster, private Members $members) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(RiskAssessment $assessment, ?int $evaluatorId): array
    {
        $position = StandingPosition::query()->findOrFail($assessment->position_id);
        $center = $this->workCenters->profile($assessment->work_center_id);

        return [
            'center_name' => $center->name ?? '',
            'center_address' => $center->address ?? '',
            'person_name' => $this->roster->find($assessment->employee_id)->name ?? '',
            'position_name' => $position->name,
            'position_tasks' => $position->tasks,
            'bipedestation' => $position->bipedestation_type->label(),
            'continuous_minutes' => $position->continuous_minutes,
            'area_conditions' => array_filter($position->area_conditions, fn (?string $text): bool => $text !== null && trim($text) !== ''),
            'tools' => $this->text($position->tools),
            'physical_load' => $this->text($position->physical_load),
            'attention_level' => $this->text($position->attention_level),
            'evaluator_name' => $evaluatorId === null ? null : $this->members->find($evaluatorId)?->name,
        ];
    }

    /**
     * What Quinta C still lacks before the assessment can be signed, in words for whoever assesses.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public function missing(RiskAssessment $assessment, array $snapshot): array
    {
        $lacks = [
            'la fecha de verificación' => $assessment->verified_on === null,
            'las condiciones del área del puesto' => Arr::array($snapshot, 'area_conditions') === [],
            'las herramientas del puesto' => ($snapshot['tools'] ?? null) === null,
            'la carga física del puesto' => ($snapshot['physical_load'] ?? null) === null,
            'el nivel de atención del puesto' => ($snapshot['attention_level'] ?? null) === null,
            'otros aspectos a considerar' => $this->text($assessment->other_aspects) === null,
        ];

        return array_keys(array_filter($lacks));
    }

    /**
     * The eleven items of the registry as the page shows them.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<array{numeral: string, label: string, value: string}>
     */
    public function facts(array $snapshot, ?CarbonImmutable $verifiedOn, ?string $otherAspects): array
    {
        $area = [];
        foreach (self::AGENTS as $key => $label) {
            $text = Arr::array($snapshot, 'area_conditions')[$key] ?? null;
            if (is_string($text)) {
                $area[] = "{$label}: {$text}";
            }
        }

        $none = '—';
        $evaluator = $snapshot['evaluator_name'] ?? null;

        return [
            ['numeral' => 'I', 'label' => 'Centro de trabajo', 'value' => trim(Arr::string($snapshot, 'center_name').' · '.Arr::string($snapshot, 'center_address'), ' ·')],
            ['numeral' => 'II', 'label' => 'Fecha de verificación', 'value' => $verifiedOn?->format('d/m/Y') ?? $none],
            ['numeral' => 'III', 'label' => 'Condiciones del área', 'value' => $area === [] ? $none : implode('; ', $area)],
            ['numeral' => 'IV', 'label' => 'Herramientas, maquinaria o equipos fijos', 'value' => $this->text(Arr::string($snapshot, 'tools', '')) ?? $none],
            ['numeral' => 'V', 'label' => 'Carga física manual', 'value' => $this->text(Arr::string($snapshot, 'physical_load', '')) ?? $none],
            ['numeral' => 'VI', 'label' => 'Nivel de atención requerido', 'value' => $this->text(Arr::string($snapshot, 'attention_level', '')) ?? $none],
            ['numeral' => 'VII', 'label' => 'Persona evaluada', 'value' => Arr::string($snapshot, 'person_name')],
            ['numeral' => 'VIII', 'label' => 'Puesto y tareas de pie', 'value' => Arr::string($snapshot, 'position_name').' — '.Arr::string($snapshot, 'position_tasks')],
            ['numeral' => 'IX', 'label' => 'Tipo de bipedestación y tiempo continuo', 'value' => Arr::string($snapshot, 'bipedestation').' · '.Arr::integer($snapshot, 'continuous_minutes').' minutos continuos'],
            ['numeral' => 'X', 'label' => 'Otros aspectos a considerar', 'value' => $this->text($otherAspects) ?? $none],
            ['numeral' => 'XI', 'label' => 'Evaluador (integrante de la Comisión)', 'value' => is_string($evaluator) && $evaluator !== '' ? $evaluator : 'Se registra al firmar'],
        ];
    }

    private function text(?string $text): ?string
    {
        $text = trim($text ?? '');

        return $text === '' ? null : $text;
    }
}
