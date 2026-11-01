<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Requests;

use App\Modules\Telework\Enums\ReviewDecision;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * The decision fields of a review, shared by the requests that carry one. Only the shape is checked; what each decision
 * needs (a plan and its date, notes) is `ReviewWorkplace`'s.
 */
trait ReadsDecision
{
    /**
     * @return array<string, mixed>
     */
    private function decisionRules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'plan_due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function userId(): int
    {
        return ($this->user() ?? abort(403))->id;
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
