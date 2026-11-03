<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use Illuminate\Support\Collection;

/**
 * What the lines of the dossier are written from, loaded once.
 */
final readonly class DossierFacts
{
    /**
     * @param  Collection<int, StandingPosition>  $positions
     * @param  Collection<int, Seat>  $seats
     * @param  Collection<int, RiskAssessment>  $signed  oldest first
     * @param  Collection<int, PreventiveMeasure>  $measures  those not cancelled
     * @param  Collection<int, PauseAgreement>  $agreements
     * @param  Collection<int, SeatingDocument>  $documents
     */
    public function __construct(
        public ?SeatingProfile $profile,
        public Collection $positions,
        public Collection $seats,
        public Collection $signed,
        public Collection $measures,
        public Collection $agreements,
        public ?InternalRegulation $regulation,
        public Collection $documents,
        public int $committeeMembers,
    ) {}
}
