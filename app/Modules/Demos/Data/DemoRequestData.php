<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;

final readonly class DemoRequestData
{
    /**
     * @param  string  $whatsapp  E.164, always a Mexican number: +52 and 10 digits.
     * @param  list<Tool>  $tools  The HR tools the prospect wants to see; at least one.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $whatsapp,
        public string $company,
        public DemoProfile $profile,
        public Workforce $workforce,
        public array $tools,
        public ?string $message,
    ) {}
}
