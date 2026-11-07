<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A person asks for one of their weeks (`start`: any date of it, Y-m-d; none for the last week that is over) or confirms
 * one, with the signature of their linked device. The signature is the credential and alone names the person.
 */
final class WeekRequest extends FormRequest
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
            'start' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function credential(): array
    {
        return $this->array('credential');
    }

    public function start(): ?CarbonImmutable
    {
        return $this->filled('start') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('start')->toString()) ?: null : null;
    }
}
