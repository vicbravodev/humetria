<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\CutAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The results are cut by one attribute at a time: two cuts at once would narrow the group until it points at someone.
 */
final class ResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['cut' => ['nullable', 'string', Rule::enum(CutAttribute::class)]];
    }

    public function cut(): ?CutAttribute
    {
        return CutAttribute::tryFrom($this->string('cut')->toString());
    }
}
