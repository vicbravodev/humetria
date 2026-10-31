<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Http\Requests;

use App\Modules\Campaigns\Enums\Population;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A new NOM-035 evaluation of a work center. Who takes Guía III (everyone or a sample) only matters where it applies;
 * the action decides that, since only it knows the headcount.
 */
final class CreateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-campaigns');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_center_id' => ['required', 'uuid'],
            'population' => ['nullable', Rule::enum(Population::class)],
        ];
    }

    public function workCenterId(): string
    {
        return $this->string('work_center_id')->toString();
    }

    public function population(): Population
    {
        return $this->enum('population', Population::class) ?? Population::All;
    }
}
