<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Requests;

use App\Modules\Results\Data\ReportDetailsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What HR fills in for an evaluation's report (num. 7.7). Everything is required but the cédula: "en su caso".
 */
final class ReportDetailsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', 'max:2000'],
            'activities' => ['required', 'string', 'max:2000'],
            'conclusions' => ['required', 'string', 'max:5000'],
            'recommendations' => ['required', 'string', 'max:5000'],
            'responsible_name' => ['required', 'string', 'max:150'],
            'responsible_license' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function details(): ReportDetailsData
    {
        $license = $this->string('responsible_license')->trim()->toString();

        return new ReportDetailsData(
            objective: $this->string('objective')->trim()->toString(),
            activities: $this->string('activities')->trim()->toString(),
            conclusions: $this->string('conclusions')->trim()->toString(),
            recommendations: $this->string('recommendations')->trim()->toString(),
            responsibleName: $this->string('responsible_name')->trim()->toString(),
            responsibleLicense: $license === '' ? null : $license,
        );
    }
}
