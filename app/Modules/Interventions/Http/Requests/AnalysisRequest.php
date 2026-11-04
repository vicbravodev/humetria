<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * HR writes the analysis of a row of the Programa.
 */
final class AnalysisRequest extends FormRequest
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
        return ['analysis' => ['nullable', 'string', 'max:4000']];
    }
}
