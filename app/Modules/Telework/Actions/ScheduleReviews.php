<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Jobs\SendPortalReminder;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The daily periodic review (criterio C-13): thirty days before a validation lapses, asks the person for the periodic
 * Sección II and tells them. A workplace that already lapsed without it is asked too, once. Blocked companies are skipped.
 */
final readonly class ScheduleReviews
{
    /** Days before the expiry the Sección II is requested. */
    public const int WARNING_DAYS = 30;

    public function __construct(private Companies $companies, private WorkplaceStanding $standing, private RequestChecklist $request) {}

    /**
     * @return int how many applications were created.
     */
    public function handle(): int
    {
        $created = 0;

        foreach (Teleworker::query()->withoutGlobalScopes()->where('status', TeleworkerStatus::Active)->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (Teleworker $teleworker): string => $teleworker->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$created): void {
                    $created += $this->schedule($tenantId);
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others from being reviewed.
                report($failure);
            }
        }

        Log::info('telework.reviews_scheduled', ['applications' => $created]);

        return $created;
    }

    private function schedule(string $tenantId): int
    {
        if ($this->companies->enter($tenantId)->blocked) {
            return 0;
        }

        $limit = CarbonImmutable::now()->setTimezone(config()->string('app.display_timezone'))->startOfDay()->addDays(self::WARNING_DAYS);
        $created = 0;
        $workplaces = Workplace::query()->whereNull('retired_at')
            ->whereIn('teleworker_id', Teleworker::query()->where('status', TeleworkerStatus::Active)->select('id'))
            ->orderBy('id')->get();
        // latestReviews only chunks the ids into a whereIn: their keys are never read.
        $reviews = $this->standing->latestReviews(array_values($workplaces->map(fn (Workplace $workplace): string => $workplace->id)->all())); // @pest-mutate-ignore: UnwrapArrayValues

        foreach ($workplaces as $workplace) {
            $review = $reviews[$workplace->id] ?? null;

            // The latest review validates and lapses within the warning days, or already lapsed (the period was shortened,
            // or nobody ran this in time): either way the person owes a Sección II, asked once per review.
            if ($review === null || ! $review->decision->validates() || $this->standing->expiry($review)->greaterThan($limit)) {
                continue;
            }

            $created += $this->requestSectionTwo($workplace, $review);
        }

        return $created;
    }

    /** One Sección II per review: while the last one asked after the latest review is not cancelled, answered or not, nobody is asked again. */
    private function requestSectionTwo(Workplace $workplace, WorkplaceReview $review): int
    {
        if (ChecklistApplication::query()->where('workplace_id', $workplace->id)->where('section', Section::Two)->whereNull('cancelled_at')->where('requested_at', '>', $review->decided_at)->exists()) {
            return 0;
        }

        try {
            $application = $this->request->handle($workplace, Section::Two);
        } catch (ValidationException) {
            // The work center has no published list yet: nothing to ask for.
            return 0;
        }

        if (! $application->wasRecentlyCreated) {
            return 0;
        }

        $teleworker = Teleworker::query()->findOrFail($workplace->teleworker_id, ['employee_id']);
        SendPortalReminder::remind($workplace->tenant_id, $teleworker->employee_id);

        return 1;
    }
}
