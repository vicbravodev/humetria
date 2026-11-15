<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use App\Modules\Engagement\Data\CommitmentData;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyKind;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A commitment of the company as RH writes it. Whether the group is shown and has room is decided by SaveCommitment. */
final class CommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $kind = $this->route('kind');

        // Before the rules: whoever may not write is told so, not what their empty form lacks.
        // The route binds the kind to its enum.
        return $kind instanceof SurveyKind && $this->user()?->can($kind->managePermission()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'group_type' => ['required', 'string', Rule::enum(CommitmentGroup::class)],
            'group_id' => ['nullable', 'uuid'],
            'driver' => ['nullable', 'string', Rule::enum(Driver::class)],
            'heard' => ['required', 'string', 'max:500'],
            'will_do' => ['required', 'string', 'max:500'],
            'owner_name' => ['required', 'string', 'max:120'],
            'due_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function commitment(string $surveyId, int $userId): CommitmentData
    {
        return new CommitmentData(
            $surveyId,
            $userId,
            CommitmentGroup::from($this->string('group_type')->toString()),
            $this->filled('group_id') ? $this->string('group_id')->toString() : null,
            $this->filled('driver') ? $this->string('driver')->toString() : null,
            $this->string('heard')->trim()->toString(),
            $this->string('will_do')->trim()->toString(),
            $this->string('owner_name')->trim()->toString(),
            CarbonImmutable::parse($this->string('due_on')->toString()),
        );
    }
}
