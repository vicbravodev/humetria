<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum group
    |--------------------------------------------------------------------------
    |
    | The fewest anonymous questionnaires a group (the work center, an area, a question) needs before any result of it is
    | shown (criterio C-23). Applied after every filter. This is what a company applies until it chooses its own, from 1
    | to 50 (`tenants.min_group`, read through Tenancy's GroupMinimums).
    |
    */

    'min_group' => min(50, max(1, (int) env('RESULTS_MIN_GROUP', 5))),

];
