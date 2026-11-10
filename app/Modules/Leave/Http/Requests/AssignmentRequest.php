<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\Enums\PolicyScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Says which policy a target follows (the company, a staff group or a job grade), or takes it away when none is sent: owner
 * and hr only. Whether the group, the grade and the policy exist is for AssignPolicy, which answers in the field.
 */
final class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vacations') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::enum(PolicyScope::class)],
            'policy_id' => ['nullable', 'uuid'],
            'staff_group' => ['nullable', 'string', 'max:20'],
            'job_grade_id' => ['nullable', 'uuid'],
        ];
    }

    public function scope(): PolicyScope
    {
        return PolicyScope::from($this->string('scope')->toString());
    }

    public function policyId(): ?string
    {
        return $this->filled('policy_id') ? $this->string('policy_id')->toString() : null;
    }

    public function staffGroup(): ?string
    {
        return $this->filled('staff_group') ? $this->string('staff_group')->toString() : null;
    }

    public function jobGradeId(): ?string
    {
        return $this->filled('job_grade_id') ? $this->string('job_grade_id')->toString() : null;
    }
}
