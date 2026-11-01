<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Support\ChecklistAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A checklist question as the Comisión writes it. Adding one also names its section and heading; editing one does not move it.
 * The access check comes first, so whoever may not edit is told 403/404 whatever they send.
 */
final class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        resolve(ChecklistAccess::class)->editable(($this->user() ?? abort(403))->id, $this->route('workCenter'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...($this->isMethod('POST') ? [
                'section' => ['required', 'integer', Rule::in([1, 2])],
                'heading' => ['required', 'string', 'max:200'],
            ] : []),
            'text' => ['required', 'string', 'max:1000'],
            'favorable' => ['required', Rule::enum(Favorable::class)],
            'sensitive' => ['boolean'],
            'requirement' => ['nullable', Rule::enum(Requirement::class)],
        ];
    }

    public function section(): int
    {
        return $this->integer('section');
    }

    public function heading(): string
    {
        return $this->string('heading')->toString();
    }

    public function questionText(): string
    {
        return $this->string('text')->toString();
    }

    public function favorable(): Favorable
    {
        return Favorable::from($this->string('favorable')->toString());
    }

    public function sensitive(): bool
    {
        return $this->boolean('sensitive');
    }

    public function requirement(): ?Requirement
    {
        return Requirement::tryFrom($this->string('requirement')->toString());
    }
}
