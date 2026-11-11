<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\CommitmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CommitmentStatusRequest extends FormRequest
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
        return ['status' => ['required', 'string', Rule::enum(CommitmentStatus::class)]];
    }

    public function status(): CommitmentStatus
    {
        return CommitmentStatus::from($this->string('status')->toString());
    }
}
