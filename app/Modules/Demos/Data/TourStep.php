<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

use App\Modules\Demos\Enums\TourKey;

/**
 * A step of a guided session's tour. Its copy is fixed and in es-MX (the panel tutea): never anything of the company, since
 * the guide on screen writes it as HTML.
 */
final readonly class TourStep
{
    /**
     * @param  string  $key  unique in the catalog, prefixed by its tour («nom035.launch»)
     * @param  string  $todo  what to do, in one sentence
     * @param  string  $why  why it matters, to the client, in one or two sentences
     * @param  'do'|'watch'  $kind  «Hazlo» (the presenter acts) or «Mira» (the audience acts, the panel shows it)
     * @param  string  $route  the named panel route («Llévame» goes there, with the session's center or evaluation)
     * @param  string|null  $target  the `data-tour` lit on that page (one of TourCatalog::TARGETS)
     * @param  string|null  $ask  a question for Humi, written for the presenter to send
     */
    public function __construct(
        public string $key,
        public TourKey $tour,
        public string $title,
        public string $todo,
        public string $why,
        public string $kind,
        public string $route,
        public ?string $target = null,
        public ?string $ask = null,
    ) {}
}
