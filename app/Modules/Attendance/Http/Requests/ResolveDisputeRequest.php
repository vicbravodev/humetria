<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\ResolveDispute;
use Illuminate\Foundation\Http\FormRequest;

/** RH resolves a person's dispute of their week with a note: the person reads it before answering again. */
final class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:'.ResolveDispute::MIN_NOTE, 'max:500']];
    }

    public function note(): string
    {
        return $this->string('note')->toString();
    }
}
