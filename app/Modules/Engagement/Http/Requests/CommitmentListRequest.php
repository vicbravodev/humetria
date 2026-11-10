<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\CommitmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The list of commitments, optionally of one status. */
final class CommitmentListRequest extends FormRequest
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
        return ['status' => ['nullable', 'string', Rule::enum(CommitmentStatus::class)]];
    }

    public function status(): ?CommitmentStatus
    {
        return CommitmentStatus::tryFrom($this->string('status')->toString());
    }
}
