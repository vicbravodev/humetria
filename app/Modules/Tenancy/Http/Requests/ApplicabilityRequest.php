<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Tenancy\Data\CenterAnswers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What one or more work centers answer to «¿Te aplica?» (boards V2-Aplica-Otras and V3-Onboarding). Whoever manages both
 * NOM-037 and Ley Silla answers; a standard left out of a center is not touched.
 */
final class ApplicabilityRequest extends FormRequest
{
    public const int MOST_CENTERS = 50;

    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true && $this->user()->can('manage-seating');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(SeatingApplicability $seating): array
    {
        return [
            'centers' => ['required', 'array', 'list', 'max:'.self::MOST_CENTERS],
            'centers.*' => ['array:id,has_teleworkers,standing,sector'],
            'centers.*.id' => ['required', 'uuid', 'distinct'],
            'centers.*.has_teleworkers' => ['nullable', 'boolean'],
            'centers.*.standing' => ['nullable', 'boolean'],
            'centers.*.sector' => ['nullable', 'required_if_accepted:centers.*.standing', 'string', Rule::in($seating->sectors())],
        ];
    }

    /**
     * @return list<CenterAnswers>
     */
    public function answers(): array
    {
        $answers = [];

        foreach (array_keys($this->array('centers')) as $index) {
            $answers[] = new CenterAnswers(
                $this->string("centers.{$index}.id")->toString(),
                $this->yesOrNo("centers.{$index}.has_teleworkers"),
                $this->yesOrNo("centers.{$index}.standing"),
                $this->filled("centers.{$index}.sector") ? $this->string("centers.{$index}.sector")->toString() : null,
            );
        }

        return $answers;
    }

    private function yesOrNo(string $key): ?bool
    {
        return $this->filled($key) ? $this->boolean($key) : null;
    }
}
