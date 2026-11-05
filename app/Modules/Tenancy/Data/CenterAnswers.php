<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * What one work center answered to «¿Te aplica?» (rediseño v2, decisión A2): whether someone works away from it more than
 * 40 % of their time (NOM-037) and whether someone works standing, with its sector (Ley Silla). A null was not asked.
 */
final readonly class CenterAnswers
{
    public function __construct(
        public string $workCenterId,
        public ?bool $hasTeleworkers,
        public ?bool $standing,
        public ?string $sector,
    ) {}
}
