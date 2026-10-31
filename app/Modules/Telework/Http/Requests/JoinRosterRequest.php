<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\MaritalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The data of numeral 5.1 that RH types to move an aspirant into the roster. The job profile is not here: it is the
 * employee's job title, read from Organization.
 */
final class JoinRosterRequest extends FormRequest
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
            'marital_status' => ['required', Rule::enum(MaritalStatus::class)],
            'activities' => ['required', 'string', 'max:2000'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/\A[^\x00-\x1F\x7F]*\z/u'],
            'no_equipment_reason' => ['required', 'string', 'max:1000'],
            ...AddressRules::for(),
        ];
    }

    public function maritalStatus(): MaritalStatus
    {
        return $this->enum('marital_status', MaritalStatus::class) ?? MaritalStatus::Single;
    }

    public function home(): AddressData
    {
        return AddressData::fromInput($this->only(array_keys(AddressRules::for())));
    }
}
