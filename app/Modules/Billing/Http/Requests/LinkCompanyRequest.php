<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class LinkCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-billing');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || resolve(CompanyRegistry::class)->find($value) === null) {
                    $fail('No existe esa empresa.');
                }
            }],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function companyId(): string
    {
        return $this->string('company_id')->toString();
    }

    /** The day the payer starts: the one typed, or today in the time zone the panel shows. */
    public function startsOn(): CarbonImmutable
    {
        return $this->filled('starts_on')
            ? CarbonImmutable::parse($this->string('starts_on')->toString())->startOfDay()
            : CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
    }
}
