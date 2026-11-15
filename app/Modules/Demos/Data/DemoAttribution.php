<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/** The first and the last known channel of whoever asked for a demo. */
final readonly class DemoAttribution
{
    public function __construct(
        public DemoTouch $first,
        public DemoTouch $last,
    ) {}

    /** The channel the conversion is credited to: the last known one, else the first. */
    public function credited(): DemoTouch
    {
        return $this->last->isEmpty() ? $this->first : $this->last;
    }
}
