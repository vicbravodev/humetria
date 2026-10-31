<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\ReviewDecision;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Comisión's decision on a workplace. Only the shape is checked here; what each decision needs (a plan and its date,
 * notes) is `ReviewWorkplace`'s. Who may send it is the controller's (`ReviewAccess`).
 */
final class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'plan_due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function decision(): ReviewDecision
    {
        return ReviewDecision::from($this->string('decision')->toString());
    }

    public function notes(): string
    {
        return $this->string('notes')->toString();
    }

    public function actionPlan(): ?string
    {
        return $this->filled('action_plan') ? $this->string('action_plan')->toString() : null;
    }

    public function planDueOn(): ?CarbonImmutable
    {
        return $this->filled('plan_due_on') ? CarbonImmutable::parse($this->string('plan_due_on')->toString()) : null;
    }
}
