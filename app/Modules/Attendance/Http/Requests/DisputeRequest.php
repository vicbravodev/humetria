<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Actions\DisputeWeek;
use Illuminate\Foundation\Http\FormRequest;

/** A person disputes a week: the signature of their linked device, why in their words, and the digest of the week they read. */
final class DisputeRequest extends FormRequest
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
            'credential' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:'.DisputeWeek::MIN_REASON, 'max:1000'],
            'digest' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }

    public function digest(): string
    {
        return $this->string('digest')->toString();
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
