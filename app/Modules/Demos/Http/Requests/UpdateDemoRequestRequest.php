<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateDemoRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the 404 of the gate: a person who is not the superadmin must not even learn the body was invalid.
        Gate::authorize('manage-demo-requests');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $route = $this->route('demoRequest');
        $converted = $route instanceof DemoRequest && $route->status === DemoStatus::Converted;

        return [
            // «Convertida» is reached by creating the company from the request, never by hand; one already converted keeps it.
            'status' => ['required', 'string', $converted ? Rule::enum(DemoStatus::class) : Rule::enum(DemoStatus::class)->except(DemoStatus::Converted)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function status(): DemoStatus
    {
        return DemoStatus::from($this->string('status')->toString());
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
