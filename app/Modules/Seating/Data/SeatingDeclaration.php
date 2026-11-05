<?php

declare(strict_types=1);

namespace App\Modules\Seating\Data;

/**
 * What a work center declared for Ley Silla: whether someone works standing, its sector (null when nobody does and none
 * was declared) and the day, as the design system writes it ("3 feb 2026").
 */
final readonly class SeatingDeclaration
{
    public function __construct(public bool $standing, public ?string $sector, public string $declaredOn) {}
}
