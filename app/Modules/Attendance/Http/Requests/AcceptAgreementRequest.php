<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A person accepts the version of the agreement they read (`agreement_version`) with the signature of their key. The
 * signature is the credential and alone names the person.
 */
final class AcceptAgreementRequest extends FormRequest
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
            'agreement_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }

    public function version(): int
    {
        return $this->integer('agreement_version');
    }
}
