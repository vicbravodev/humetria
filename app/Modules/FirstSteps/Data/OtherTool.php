<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Data;

/** Another evaluation tool the company has, offered next to the one its third step points at. */
final readonly class OtherTool
{
    public function __construct(public string $key, public string $href) {}
}
