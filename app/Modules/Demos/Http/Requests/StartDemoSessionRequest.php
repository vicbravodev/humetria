<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Starts a demo session: the superadmin only; the client's name, optional, and the tours to show, in their order. */
final class StartDemoSessionRequest extends FormRequest
{
    /** The tours one may choose; «base» is not among them: it always goes first. */
    private const array TOURS = ['nom035', 'attendance', 'engagement', 'complaints', 'leave', 'humi'];

    public function authorize(): bool
    {
        // Throws the gate's 404: whoever is not the superadmin must not even learn the room exists.
        Gate::authorize('manage-demo');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'client_name' => ['nullable', 'string', 'max:80'],
            'tours' => ['required', 'array', 'list', 'min:1', 'max:'.count(self::TOURS)],
            'tours.*' => ['required', 'string', 'distinct', 'in:'.implode(',', self::TOURS)],
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
