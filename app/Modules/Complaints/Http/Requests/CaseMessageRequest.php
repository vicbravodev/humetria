<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\HeldCase;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** What a handler writes in a case: a message to the person or an internal note. Neither is written on a closed case. */
final class CaseMessageRequest extends FormRequest
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
        return ['body' => ['required', 'string', 'max:'.config()->integer('complaints.message_max'), 'regex:/\S/u', $this->caseIsOpen(...)]];
    }

    public function case(): Complaint
    {
        $id = $this->route('complaint');

        return $this->case ??= resolve(HeldCase::class)->of($this, is_string($id) ? $id : '');
    }

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }

    /** @param  Closure(string): mixed  $fail */
    private function caseIsOpen(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->case()->status === ComplaintStatus::Closed) {
            $fail('Este caso está cerrado: ábrelo de nuevo para escribir.');
        }
    }
}
