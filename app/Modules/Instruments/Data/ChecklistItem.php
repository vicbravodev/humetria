<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A question of the NOM-037 verification checklist. `fromDof` marks the literal Apéndice 3 questions; the rest are
 * Humetría's, never presented as DOF text.
 */
final readonly class ChecklistItem
{
    public function __construct(
        public string $code,
        public int $section,
        public string $heading,
        public string $text,
        public Favorable $favorable,
        public ?Requirement $requirement,
        public bool $sensitive,
        public bool $fromDof,
    ) {}
}
