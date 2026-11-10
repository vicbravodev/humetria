<?php

declare(strict_types=1);

/*
| The knobs of Clima y pulso. The minimum people per group is not here: it is `results.min_group`, the one place every
| tool takes it from. The two minimums below can be raised from the environment but never lowered under their floor,
| because a lower one would let a few people be told apart in a comment or a trend.
*/
return [
    /*
    | Comments a group needs before its comments (or their summary) are shown.
    */
    'min_comments' => max(10, (int) env('ENGAGEMENT_MIN_COMMENTS', 10)),

    /*
    | Responses needed before the recommendation index and the arrows of a trend are shown.
    */
    'min_trend' => max(30, (int) env('ENGAGEMENT_MIN_TREND', 30)),

    /*
    | Characters an open answer may have.
    */
    'comment_max_length' => 1000,

    /*
    | Comments that must back a theme of the summary of a manager's comments.
    */
    'min_theme_support' => 3,
];
