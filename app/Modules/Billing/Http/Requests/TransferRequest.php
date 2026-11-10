<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\TransferData;
use App\Modules\Billing\Enums\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The move of a company between payers, to preview it (the reason may still be blank) or to do it (the action asks for it). */
final class TransferRequest extends FormRequest
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
            'to_account' => ['nullable', 'uuid'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
            'profile' => ['nullable', Rule::enum(Profile::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['to_account' => 'cuenta de destino', 'effective_on' => 'fecha efectiva', 'reason' => 'motivo', 'profile' => 'perfil de cobro'];
    }

    public function toData(): TransferData
    {
        return new TransferData(
            $this->filled('to_account') ? $this->string('to_account')->toString() : null,
            CarbonImmutable::parse($this->string('effective_on')->toString())->startOfDay(),
            $this->string('reason')->toString(),
            $this->filled('profile') ? Profile::from($this->string('profile')->toString()) : null,
        );
    }
}
