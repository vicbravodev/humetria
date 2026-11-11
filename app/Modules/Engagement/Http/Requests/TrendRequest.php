<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The trend is of the whole company and takes nothing: a cut would multiply the comparisons between waves with groups
 * whose people change from one to the next.
 */
final class TrendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['cut' => ['prohibited']];
    }
}
