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
    | Marking without a connection (criterio 3.3.1). `enabled` is the only switch: it turns the phone's queue and screens off
    | (the phone reads it from the context it receives) and never the delivery. The rest are constants, not settings: the
    | tickets that bound the time of such a mark are signed with the app key (and APP_PREVIOUS_KEYS).
    */
    'offline' => [
        'enabled' => (bool) env('ATTENDANCE_OFFLINE_ENABLED', true),
        // Tickets handed to the phone at a time, and how old one may be and still serve (the horizon of a correction).
        'ticket_batch' => 8,
        'ticket_horizon_days' => 35,
        // The time a person declares counts up to this many hours after the ticket was issued; past them only what is proven.
        'count_declared_max_hours' => 72,
        // An interval wider than this (receipt minus ticket) is flagged `wide_interval`.
        'wide_interval_minutes' => 240,
        // A QR read more than this before the declared time is treated as having none (`old_window`).
        'qr_old_window_minutes' => 30,
        // A copy in the queue of a mark made with a connection, within this of the same type, is «ya registrada».
        'already_recorded_window_minutes' => 10,
        // Marks in one delivery.
        'delivery_batch_max' => 20,
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

    /*
    | «Mi semana»: a person cannot confirm a week with days left without cover (a working day of their schedule, already
    | over, with no marks and no holiday, hire date or approved absence: criterio 5.2). It holds for every company. With
    | `false` the days are still computed and logged (`attendance.week_uncovered_days_computed`), but nothing is blocked.
    */
    'week' => [
        'block_uncovered_days' => (bool) env('ATTENDANCE_WEEK_BLOCK_UNCOVERED', true),
    ],
];
