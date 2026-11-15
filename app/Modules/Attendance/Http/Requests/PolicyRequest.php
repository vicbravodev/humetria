<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use App\Modules\Attendance\Enums\OutOfZoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The company's policy: what a mark out of every zone does, the worst accuracy a position may have (10 to 500 m), whether
 * marking on site needs the QR, whether trusted staff may mark from a browser, and how many months the records are kept:
 * at least 12 (criterio 7.1), at most 10 years, and the minutes of tolerance (0 to 120) before a start counts as late: shown, never
 * sanctioned. A switch left off is a no.
 */
final class PolicyRequest extends FormRequest
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
        return [
            // Rule::in, not Rule::enum: the enum rule ignores the custom message of lang/es/validation.php.
            'out_of_zone' => ['required', Rule::in(array_column(OutOfZoneRule::cases(), 'value'))],
            'max_accuracy_m' => ['required', 'integer', 'between:10,500'],
            'require_qr_onsite' => ['boolean'],
            'trusted_may_use_browser' => ['boolean'],
            'retention_months' => ['required', 'integer', 'between:12,120'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,120'],
        ];
    }

    public function outOfZone(): OutOfZoneRule
    {
        return $this->enum('out_of_zone', OutOfZoneRule::class) ?? OutOfZoneRule::Block;
    }

    public function maxAccuracy(): int
    {
        return $this->integer('max_accuracy_m');
    }

    public function requireQrOnsite(): bool
    {
        return $this->boolean('require_qr_onsite');
    }

    public function trustedMayUseBrowser(): bool
    {
        return $this->boolean('trusted_may_use_browser');
    }

    public function retentionMonths(): int
    {
        return $this->integer('retention_months');
    }

    public function lateToleranceMinutes(): int
    {
        return $this->integer('late_tolerance_minutes');
    }
}
