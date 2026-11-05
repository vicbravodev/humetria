<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Telework\Contracts\TeleworkApplicability;
use App\Modules\Tenancy\Data\CenterAnswers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps what each work center answered to «¿Te aplica?» in the module that owns each standard, all or nothing: NOM-037's
 * declaration in Telework and Ley Silla's in Seating, each audited and logged there. NOM-035 applies to every center and
 * asks nothing.
 */
final readonly class RecordApplicability
{
    public function __construct(private TeleworkApplicability $telework, private SeatingApplicability $seating) {}

    /**
     * @param  list<CenterAnswers>  $answers
     */
    public function handle(array $answers, int $userId): void
    {
        DB::transaction(function () use ($answers, $userId): void {
            foreach ($answers as $center) {
                if ($center->hasTeleworkers !== null) {
                    $this->telework->declare($center->workCenterId, $center->hasTeleworkers, $userId);
                }

                if ($center->standing !== null) {
                    $this->seating->declare($center->workCenterId, $center->standing, $center->sector, $userId);
                }
            }

            Log::info('tenancy.applicability_answered', ['work_center_ids' => array_map(fn (CenterAnswers $center): string => $center->workCenterId, $answers)]);
        });
    }
}
