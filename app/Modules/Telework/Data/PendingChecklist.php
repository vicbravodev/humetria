<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * A checklist asked of the person, as their portal lists it. `label` never says how the Comisión judged it.
 */
final readonly class PendingChecklist
{
    public function __construct(public string $id, public string $workplace, public int $section, public string $label) {}
}
