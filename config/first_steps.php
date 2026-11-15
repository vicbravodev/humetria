<?php

declare(strict_types=1);

/*
| Primeros pasos: the guided tour of a new company's owner. The switch lets a deployment (and the test suite, where it is
| off so no page grows a welcome dialog) turn the whole thing off.
*/
return [
    'enabled' => (bool) env('FIRST_STEPS_ENABLED', true),
];
