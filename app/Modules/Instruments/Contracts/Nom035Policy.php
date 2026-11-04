<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use App\Modules\Instruments\Data\PolicyTemplate;

/**
 * The example policy of Guía de referencia IV that a company starts its policy of prevention from (num. 5.1). It is only a
 * template: the guide is not mandatory, so the company edits it before publishing.
 */
interface Nom035Policy
{
    /** The DOF edition the example is transcribed from. */
    public function version(): string;

    public function template(): PolicyTemplate;
}
