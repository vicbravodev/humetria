<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\HeldCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** How a handler closes a case, and what it answers the person. «Withdrawn» is the person's to give, not the handler's. */
final class CloseCaseRequest extends FormRequest
{
    private ?Complaint $case = null;

    public function authorize(): bool
    {
        $this->case();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(array_map(fn (ComplaintResolution $resolution): string => $resolution->value, ComplaintResolution::handlerChoices()))],
            'body' => ['nullable', 'string', 'max:'.config()->integer('complaints.message_max')],
        ];
    }

    public function case(): Complaint
    {
        $id = $this->route('complaint');

        return $this->case ??= resolve(HeldCase::class)->of($this, is_string($id) ? $id : '');
    }

    public function resolution(): ComplaintResolution
    {
        return ComplaintResolution::from($this->string('resolution')->toString());
    }

    /** The answer to the person, trimmed; null when nothing was written. */
    public function answer(): ?string
    {
        $answer = trim($this->string('body')->toString());

        return $answer === '' ? null : $answer;
    }
}
