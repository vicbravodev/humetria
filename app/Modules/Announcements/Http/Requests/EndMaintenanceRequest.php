<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Ending the maintenance: nothing to send, only who may. */
final class EndMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage-announcements');

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
