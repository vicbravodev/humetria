<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Enums\TourKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

/** Starts a demo session: the superadmin only; the client's name, optional, and the tours to show, in their order. */
final class StartDemoSessionRequest extends FormRequest
{
    /** No line breaks nor control characters, as TenantRules::SINGLE_LINE (internal to Tenancy, so repeated here). */
    private const string SINGLE_LINE = 'regex:/\A[^\x00-\x1F\x7F]*\z/u';

    public function authorize(): bool
    {
        // Throws the gate's 404: whoever is not the superadmin must not even learn the room exists.
        Gate::authorize('manage-demo');

        return true;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        // The tours one may choose; «base» is not among them: it always goes first.
        $choosable = count(TourKey::cases()) - 1;

        return [
            'client_name' => ['nullable', 'string', 'max:80', self::SINGLE_LINE],
            'tours' => ['required', 'array', 'list', 'min:1', 'max:'.$choosable],
            'tours.*' => ['required', 'string', 'distinct', (new Enum(TourKey::class))->except(TourKey::Base)],
        ];
    }

    public function clientName(): ?string
    {
        return $this->filled('client_name') ? $this->string('client_name')->toString() : null;
    }

    /** @return list<string> */
    public function tours(): array
    {
        return array_map(fn (int|string $index): string => $this->string("tours.{$index}")->toString(), array_keys($this->array('tours')));
    }
}
