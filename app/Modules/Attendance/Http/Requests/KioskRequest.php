<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A new QR screen: the work center it shows the code of. */
final class KioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['work_center_id' => ['required', 'uuid']];
    }
}
