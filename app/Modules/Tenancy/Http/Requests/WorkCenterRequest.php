<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\WorkCenter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of a work center, to add one or to correct it. ResolveTenant has already checked that the person
 * belongs to the company; the global scope keeps the name check inside it.
 *
 * @phpstan-import-type FieldRules from TenantRules
 */
final class WorkCenterRequest extends FormRequest
{
    /**
     * @return array<string, FieldRules>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', TenantRules::SINGLE_LINE, $this->uniqueName(...)],
            'street' => ['required', 'string', 'max:150', TenantRules::SINGLE_LINE],
            'exterior_number' => ['required', 'string', 'max:20', TenantRules::SINGLE_LINE],
            'interior_number' => ['nullable', 'string', 'max:20', TenantRules::SINGLE_LINE],
            'neighborhood' => ['required', 'string', 'max:120', TenantRules::SINGLE_LINE],
            'postal_code' => ['required', 'string', 'regex:/\A[0-9]{5}\z/'],
            'municipality' => ['required', 'string', 'max:120', TenantRules::SINGLE_LINE],
            'state' => ['required', 'string', Rule::in(array_column(MexicanState::cases(), 'value'))],
            'main_activity' => ['required', 'string', 'max:200', TenantRules::SINGLE_LINE],
        ];
    }

    public function toData(): WorkCenterData
    {
        $interiorNumber = $this->string('interior_number')->toString();

        return new WorkCenterData(
            name: $this->string('name')->toString(),
            street: $this->string('street')->toString(),
            exteriorNumber: $this->string('exterior_number')->toString(),
            interiorNumber: $interiorNumber === '' ? null : $interiorNumber,
            neighborhood: $this->string('neighborhood')->toString(),
            postalCode: $this->string('postal_code')->toString(),
            municipality: $this->string('municipality')->toString(),
            state: MexicanState::from($this->string('state')->toString()),
            mainActivity: $this->string('main_activity')->toString(),
        );
    }

    /**
     * The name is unique inside the company whatever the case, archived work centers included, as the database's
     * index says; the one being corrected keeps its own.
     *
     * @param  Closure(string, string|null=): mixed  $fail
     */
    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $editing = $this->route('workCenter');

        $taken = is_string($value) && WorkCenter::query()
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->when(is_string($editing), fn ($query) => $query->whereKeyNot($editing))
            ->exists();

        if ($taken) {
            $fail('Ya hay un centro de trabajo con este nombre.');
        }
    }
}
