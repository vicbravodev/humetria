<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;

final readonly class DemoRequestData
{
    /**
     * @param  string  $whatsapp  E.164 (`+525512345678`), of Mexico or of another country.
     * @param  list<Tool>  $tools  The HR tools the prospect wants to see; none when they did not say.
     * @param  ?DemoAttribution  $attribution  Where the person came from, when it is known.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $whatsapp,
        public string $company,
        public ?DemoProfile $profile,
        public ?Workforce $workforce,
        public array $tools,
        public ?string $message,
        public ?DemoAttribution $attribution = null,
    ) {}
}
