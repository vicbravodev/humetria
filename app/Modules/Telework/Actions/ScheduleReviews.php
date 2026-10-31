<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Jobs\SendPortalReminder;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Contracts\Companies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The daily periodic review (criterio C-13): thirty days before a validation lapses, asks the person for the periodic
 * Sección II and tells them. Expired workplaces need nothing recorded: they already show as pending, and the staff digest
 * reports them. Blocked companies are skipped.
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

        $limit = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay()->addDays(self::WARNING_DAYS);
        $created = 0;
        $workplaces = Workplace::query()->whereNull('retired_at')
            ->whereIn('teleworker_id', Teleworker::query()->where('status', TeleworkerStatus::Active)->select('id'))
            ->orderBy('id')->get();

        foreach ($workplaces as $workplace) {
            $expires = $this->standing->expiresOn($workplace);

            if (! in_array($this->standing->of($workplace), [WorkplaceStatus::Valid, WorkplaceStatus::ValidWithPlan], true) || $expires === null || $expires->greaterThan($limit)) {
                continue;
            }

            $created += $this->requestSectionTwo($workplace);
        }

        return $created;
    }

    private function requestSectionTwo(Workplace $workplace): int
    {
        if (ChecklistApplication::query()->where('workplace_id', $workplace->id)->where('section', Section::Two)->whereNull('submitted_at')->whereNull('cancelled_at')->exists()) {
            return 0;
        }

        try {
            $this->request->handle($workplace, Section::Two);
        } catch (ValidationException) {
            // The work center has no published list yet: nothing to ask for.
            return 0;
        }

        $teleworker = Teleworker::query()->findOrFail($workplace->teleworker_id, ['id', 'employee_id']);
        SendPortalReminder::dispatch($workplace->tenant_id, $teleworker->employee_id, null)->delay(SendPortalReminder::DELAY);

        return 1;
    }
}
