<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * Where a work center stands in the six-stage route of a standard (rediseño v2, boards V2-Ruta-N035, V2-Ruta-N037 and
 * V2-Ruta-Silla), as each compliance module derives it and the panel draws it: which of the six stages are done, in the
 * standard's order, and a short line in plain words about the earliest one left («define las reglas»). A standard that
 * does not apply to the center says so instead, with why («Nadie teletrabaja en este centro»).
 */
final readonly class RouteProgress
{
    public const int STAGES = 6;

    /**
     * @param  list<bool>  $done  the six stages, in order; empty when it does not apply
     */
    public function __construct(
        public bool $applies,
        public array $done,
        public string $status,
    ) {}

    public static function notApplicable(string $why): self
    {
        return new self(false, [], $why);
    }
}
