<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/** The first and the last known channel of whoever asked for a demo, and the last ad click when consent allowed keeping it. */
final readonly class DemoAttribution
{
    public function __construct(
        public DemoTouch $first,
        public DemoTouch $last,
        public ?AdClick $adClick = null,
    ) {}

    /** The channel the conversion is credited to: the last known one, else the first. */
    public function credited(): DemoTouch
    {
        return $this->last->isEmpty() ? $this->first : $this->last;
    }
}
