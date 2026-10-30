<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum group
    |--------------------------------------------------------------------------
    |
    | The fewest anonymous questionnaires a group (the work center, an area, a question) needs before any result of it is
    | shown (criterio C-23). Applied after every filter. Never below 5.
    |
    */

    'min_group' => max(5, (int) env('RESULTS_MIN_GROUP', 5)),

];
