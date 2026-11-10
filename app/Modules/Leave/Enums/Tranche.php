<?php

declare(strict_types=1);

namespace App\Modules\Leave\Enums;

/**
 * The two parts of a vacation period: the days the law gives and the ones the company's policy adds on top
 * (criterio C-14).
 */
enum Tranche: string
{
    case Statutory = 'statutory';
    case Extra = 'extra';
}
