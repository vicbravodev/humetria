<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Seating one more person on a Comisión: a user of the company with a reviewing role.
 */
final class CommitteeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-telework') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::in(array_map(fn ($member): int => $member->userId, resolve(Members::class)->reviewers()))],
        ];
    }

    public function memberId(): int
    {
        return $this->integer('user_id');
    }
}
