<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Data\AddressData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One more workplace for a teleworker: an address.
 */
final class WorkplaceRequest extends FormRequest
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
        return AddressRules::for();
    }

    public function address(): AddressData
    {
        return AddressData::fromInput($this->only(array_keys(AddressRules::for())));
    }
}
