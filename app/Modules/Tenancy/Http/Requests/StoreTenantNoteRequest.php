<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** An internal note about a company: the whole staff writes them, support included. */
final class StoreTenantNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not on the staff must not even learn the body was invalid.
        Gate::authorize('view-console');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'nota'];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->toString();
    }
}
