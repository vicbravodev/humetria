<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Who at Humetría answers for the chosen companies: someone on the staff, or nobody. */
final class AssignAccountManagerRequest extends FormRequest
{
    use ChoosesCompanies;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            'user_id' => ['present', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $id = filter_var($value, FILTER_VALIDATE_INT);

                if ($id === false || User::query()->find($id)?->isStaff() !== true) {
                    $fail('Elige a alguien del equipo de Humetría.');
                }
            }],
        ];
    }

    public function manager(): ?User
    {
        return $this->filled('user_id') ? User::query()->find($this->integer('user_id')) : null;
    }
}
