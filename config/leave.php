<?php

declare(strict_types=1);

/*
| The operational knobs of Vacaciones. The days by seniority, the premium floor and the windows of each period are law
| (docs/normas/vacaciones/criterios.md), not configuration: they live in App\Modules\Leave\Support.
*/
return [
    /*
    | Business days (Monday to Friday, without the statutory holidays) a manager has to answer a request before it
    | reaches RH (criterio C-18).
    */
    'escalation_business_days' => (int) env('LEAVE_ESCALATION_BUSINESS_DAYS', 3),

    /*
    | Days before the six-month window of a period closes from which RH sees it as about to close (criterio C-09).
    */
    'enjoy_window_warning_days' => (int) env('LEAVE_ENJOY_WINDOW_WARNING_DAYS', 60),

    /*
    | Rows a file of opening balances may bring.
    */
    'import_max_rows' => (int) env('LEAVE_IMPORT_MAX_ROWS', 2000),
];
