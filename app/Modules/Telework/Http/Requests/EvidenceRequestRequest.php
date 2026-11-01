<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the Comisión asks for about a workplace. The access check comes first.
 */
final class EvidenceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        resolve(ReviewAccess::class)->ofWorkplace(($this->user() ?? abort(403))->id, $this->route('workplace'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['kind' => ['required', Rule::enum(EvidenceKind::class)], 'note' => ['nullable', 'string', 'max:1000']];
    }

    public function kind(): EvidenceKind
    {
        return EvidenceKind::from($this->string('kind')->toString());
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }
}
