<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Anything in the demo room: the console's staff only. Nothing is sent but the button pressed. */
final class DemoRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Throws the gate's 404: whoever is not on the staff must not even learn the room exists.
        Gate::authorize('view-console');

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
