<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ManagerRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The direct manager of a person, fixed on its own: someone who may be it, or nobody.
 */
final class ManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-org-chart');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['manager_id' => ['nullable', 'uuid']];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(ManagerRules $managers): array
    {
        return [function (Validator $validator) use ($managers): void {
            $id = $this->route('employee');
            $manager = $this->managerId();

            // Somebody who is not there is the controller's to answer.
            if ($manager === null || $validator->errors()->has('manager_id') || ! is_string($id) || ! Employee::query()->whereKey($id)->exists()) {
                return;
            }

            $violation = $managers->violation($id, $manager);

            if ($violation !== null) {
                $validator->errors()->add('manager_id', $violation);
            }
        }];
    }

    /** Whom the person will report to; nobody when the field came empty. */
    public function managerId(): ?string
    {
        return $this->filled('manager_id') ? $this->string('manager_id')->toString() : null;
    }
}
