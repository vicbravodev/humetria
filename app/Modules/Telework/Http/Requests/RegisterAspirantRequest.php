<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Registering an aspirant from a work center: who (an active person of that center), how much of the time they telework
 * (over 40 %) and at least one workplace.
 */
final class RegisterAspirantRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->can('manage-telework') !== true) {
            return false;
        }

        // A work center outside the company, archived or unknown is a 404 before anything is validated.
        $center = $this->route('workCenter');
        abort_unless(is_string($center) && Str::isUuid($center) && resolve(WorkCenters::class)->findActive($center) !== null, 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', $this->activeInThisCenter(...)],
            'telework_percentage' => ['required', 'integer', 'min:41', 'max:100'],
            'workplaces' => ['required', 'array', 'min:1', 'max:10'],
            'workplaces.*' => ['array'],
            ...AddressRules::for('workplaces.*.'),
        ];
    }

    public function employeeId(): string
    {
        return $this->string('employee_id')->toString();
    }

    public function percentage(): int
    {
        return $this->integer('telework_percentage');
    }

    /** @return list<AddressData> */
    public function addresses(): array
    {
        return array_values(array_map(AddressData::fromInput(...), $this->array('workplaces')));
    }

    /**
     * The Roster only knows the people of the current company, so a person of another company is simply not found.
     *
     * @param  Closure(string, string|null=): mixed  $fail
     */
    private function activeInThisCenter(string $attribute, mixed $value, Closure $fail): void
    {
        $person = is_string($value) && Str::isUuid($value) ? resolve(Roster::class)->find($value) : null;

        if ($person === null || ! $person->active || $person->workCenterId !== $this->route('workCenter')) {
            $fail('Elige a una persona activa de este centro de trabajo.');
        }
    }
}
