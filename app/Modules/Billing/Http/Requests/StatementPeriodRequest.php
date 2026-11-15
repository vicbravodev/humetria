<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** The month a statement is for, as "2026-12": to generate its draft or to preview it. */
final class StatementPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['period' => ['required', 'string', 'date_format:Y-m']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['period' => 'periodo'];
    }

    public function month(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->string('period')->toString()) ?: CarbonImmutable::now();
    }
}
