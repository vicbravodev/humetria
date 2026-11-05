<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Requests;

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\HeldCase;
use Illuminate\Foundation\Http\FormRequest;

/** The acknowledgement of a case, with the words the handler adjusted from its draft; without them, the platform's own. */
final class AcknowledgeRequest extends FormRequest
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
        return ['body' => ['nullable', 'string', 'max:'.config()->integer('complaints.message_max')]];
    }

    public function case(): Complaint
    {
        $id = $this->route('complaint');

        return $this->case ??= resolve(HeldCase::class)->of($this, is_string($id) ? $id : '');
    }

    /** The words to the person, trimmed; null when nothing was written. */
    public function text(): ?string
    {
        $text = trim($this->string('body')->toString());

        return $text === '' ? null : $text;
    }
}
