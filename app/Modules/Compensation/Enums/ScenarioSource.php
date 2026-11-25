<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Enums;

/** What a scenario is costed on: the people of the company, or an average payroll typed in the scenario. */
enum ScenarioSource: string
{
    case Roster = 'roster';
    case Average = 'average';
}
