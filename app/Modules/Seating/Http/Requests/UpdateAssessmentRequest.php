<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Instruments\Data\ConditionDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the Comisión changes in a draft: some of the seven conditions (1 to 3), the other aspects of Quinta C X and the
 * verification date. Only what is sent changes.
 */
final class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assess-seating') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['nullable', 'array'],
            'scores.*' => ['integer', 'between:1,3'],
            'other_aspects' => ['nullable', 'string', 'max:2000'],
            'verified_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $known = array_map(fn (ConditionDefinition $condition): string => $condition->key, resolve(SeatingAssessment::class)->definition()->conditions);

            foreach (array_keys($this->array('scores')) as $key) {
                if (! in_array($key, $known, true)) {
                    $validator->errors()->add("scores.{$key}", 'Esa condicionante no existe.');
                }
            }
        });
    }

    /** @return array<string, int> */
    public function scores(): array
    {
        $scores = [];

        foreach ($this->array('scores') as $key => $points) {
            $scores[(string) $key] = is_numeric($points) ? (int) $points : 0;
        }

        return $scores;
    }

    /** @return array{other_aspects?: string|null, verified_on?: string|null} */
    public function details(): array
    {
        $details = [];

        if ($this->has('other_aspects')) {
            $text = trim($this->string('other_aspects')->toString());
            $details['other_aspects'] = $text === '' ? null : $text;
        }

        if ($this->has('verified_on')) {
            $details['verified_on'] = $this->filled('verified_on') ? $this->string('verified_on')->toString() : null;
        }

        return $details;
    }
}
