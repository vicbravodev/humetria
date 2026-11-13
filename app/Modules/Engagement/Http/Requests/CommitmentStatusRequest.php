<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\SurveyKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CommitmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kind = $this->route('kind');

        // The route binds the kind to its enum.
        return $kind instanceof SurveyKind && $this->user()?->can($kind->managePermission()) === true;
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
