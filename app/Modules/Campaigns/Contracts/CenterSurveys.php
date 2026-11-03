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
     * Closes the survey of the center that is still open, before its last day: from then on no link works and its results can
     * be read.
     *
     * @throws ValidationException when the center has no open survey.
     */
    public function close(string $workCenterId): CenterSurvey;

    /**
     * The surveys of a center, newest first.
     *
     * @return list<CenterSurvey>
     */
    public function forCenter(string $workCenterId): array;
}
