<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;

/** Where the door works: inside the demo company and nowhere else. */
final readonly class DemoDoor
{
    public function __construct(private DemoCompanies $demos, private Companies $companies) {}

    /** Enters the demo company and gives its id; null, entering nothing, when there is none. A blocked one also gives null. */
    public function enter(): ?string
    {
        $id = $this->demos->id();

        if ($id === null) {
            return null;
        }

        return $this->companies->enter($id)->blocked ? null : $id;
    }
}
