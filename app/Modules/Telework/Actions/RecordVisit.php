<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The Comisión records an in-person visit and decides on it. A visit needs the person's written authorization (given in
 * the portal or attached as a PDF) for this workplace, used once, and cannot be dated in the future (Mexico City).
 */
final readonly class RecordVisit
{
    public function __construct(private ReviewWorkplace $review) {}

    /**
     * @throws ValidationException
     */
    public function handle(Workplace $workplace, VisitAuthorization $authorization, CarbonImmutable $visitedOn, ReviewDecision $decision, string $notes, ?string $actionPlan, ?CarbonImmutable $planDueOn, int $userId): WorkplaceReview
    {
        return DB::transaction(function () use ($workplace, $authorization, $visitedOn, $decision, $notes, $actionPlan, $planDueOn, $userId): WorkplaceReview {
            // Locked: two members cannot use the same authorization at once.
            $fresh = VisitAuthorization::query()->where('workplace_id', $workplace->id)->lockForUpdate()->find($authorization->id);
            $errors = [];

            if ($fresh === null || ! $fresh->authorized() || WorkplaceReview::query()->where('visit_authorization_id', $fresh->id)->exists()) {
                $errors['visit_authorization_id'] = 'Se necesita la autorización escrita de la persona, que no se haya usado en otra visita.';
            }

            if ($visitedOn->toDateString() > CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()) {
                $errors['visited_on'] = 'La visita no puede ser en el futuro.';
            }

            if ($errors !== [] || $fresh === null) {
                throw ValidationException::withMessages($errors);
            }

            $review = $this->review->handle($workplace, null, $decision, $notes, $actionPlan, $planDueOn, $userId, $fresh, $visitedOn);
            Log::info('telework.visit_recorded', ['review_id' => $review->id, 'workplace_id' => $workplace->id, 'visit_authorization_id' => $fresh->id]);

            return $review;
        });
    }
}
