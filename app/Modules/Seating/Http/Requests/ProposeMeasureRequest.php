<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Requests;

use App\Modules\Seating\Enums\MeasureKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The measure the Comisión proposes from an assessment: its kind and, for «other», what it is. Whether the user sits on the
 * center's Comisión is the controller's to say.
 */
final class ProposeMeasureRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(MeasureKind::class)],
            'note' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn (): bool => $this->input('kind') === MeasureKind::Other->value)],
        ];
    }

    public function kind(): MeasureKind
    {
        return MeasureKind::from($this->string('kind')->toString());
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
