<?php

declare(strict_types=1);

/*
| The time clock's operational knobs. The legal limits of the working day are law, not configuration: they live in
| App\Modules\Attendance\Support\LegalLimits.
*/
return [
    /*
    | The code a center's screen shows changes every `window_seconds`; the server also accepts the code of the
    | `past_windows` windows before, so a person scanning at the turn still marks.
    */
    'qr' => [
        'window_seconds' => (int) env('ATTENDANCE_QR_WINDOW_SECONDS', 30),
        'past_windows' => (int) env('ATTENDANCE_QR_PAST_WINDOWS', 1),
        // A code that counted when the page of Marcar opened serves that browser these minutes, to accept and mark.
        'scan_minutes' => (int) env('ATTENDANCE_QR_SCAN_MINUTES', 3),
        // Humetría's code on the screens (rounded modules in the brand ink, level H, the arch at the center) instead of
        // the plain one. Off until it is scanned with a low-end Android at 1 to 2 m and low brightness.
        'branded' => (bool) env('ATTENDANCE_QR_BRANDED', false),
    ],

    /*
    | Minutes the Marcar page of a browser remembers who marked last, to offer their next step.
    */
    'punch' => [
        'remember_minutes' => (int) env('ATTENDANCE_PUNCH_REMEMBER_MINUTES', 10),
    ],

    /*
    | The report for the authority: the longest period shown on screen (figures recomputed on each visit) and the longest
    | one the CSV exports, in days; and how many people it reads at a time.
    */
    'report' => [
        'page_max_days' => (int) env('ATTENDANCE_REPORT_PAGE_MAX_DAYS', 93),
        'export_max_days' => (int) env('ATTENDANCE_REPORT_EXPORT_MAX_DAYS', 366),
        'chunk_size' => (int) env('ATTENDANCE_REPORT_CHUNK_SIZE', 200),
    ],
];
