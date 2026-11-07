<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Support\DeviceLabel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A person links their device: the agreement accepted (and the version they read), a name for the device and the key it
 * created. The enrollment link in the address is the credential; there is no account to authorize.
 */
final class EnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agreement_accepted' => ['accepted'],
            'agreement_version' => ['required', 'integer', 'min:1'],
            'device_label' => ['required', 'string', 'max:60', 'regex:'.DeviceLabel::PATTERN],
            'credential' => ['required', 'array'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }
}
