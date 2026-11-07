<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A person confirms a week: the signature of their linked device and the digest of the week they read (WeekDigest). */
final class ConfirmRequest extends FormRequest
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
}
