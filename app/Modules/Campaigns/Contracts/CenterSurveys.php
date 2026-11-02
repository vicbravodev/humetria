<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Contracts;

use App\Modules\Campaigns\Data\CenterSurvey;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The confidential survey of a work center that another tool applies through Campaigns (Ley Silla): launch it for everyone
 * in the center and see the ones the center had.
 */
interface CenterSurveys
{
    /**
     * Launches it at once, with no sample, to every active person of the center; those with an email are invited and the
     * rest wait.
     *
     * @throws ValidationException when the center is not in use, has nobody, or already has one open.
     */
    public function launch(string $workCenterId, CarbonImmutable $closesOn, int $createdBy): CenterSurvey;

    /**
     * The surveys of a center, newest first.
     *
     * @return list<CenterSurvey>
     */
    public function forCenter(string $workCenterId): array;
}
