<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Requests;

use App\Modules\Compensation\Models\ReportShare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** How long a new link to a report lasts: 1 to 7 days. */
final class ShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-compensation');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['days' => ['required', 'integer', 'min:1', 'max:'.ReportShare::MAX_DAYS]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['days' => 'duración'];
    }
}
