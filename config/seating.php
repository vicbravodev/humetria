<?php

declare(strict_types=1);

return [
    /*
    | Months after which a signed risk assessment is due again. The Disposiciones set no term (criterio C-07): it is ours.
    */
    'reassessment_months' => 12,

    /*
    | Days an open seat report may wait before the daily digest warns about it.
    */
    'report_alert_days' => 15,

    /*
    | The range of the fine, in UMA, per affected person (LFT 992 and 1002; criterio C-15). The law sets no fine of its own
    | for this obligation. Source: Ley Federal del Trabajo, checked on 2026-11-02. Never a peso amount: the UMA changes yearly.
    */
    'fine_uma_min' => 50,
    'fine_uma_max' => 5000,

    /*
    | Largest file, in kilobytes, that can be uploaded as evidence: photos of a seat (jpeg or png) and documents (pdf).
    */
    'max_photo_kb' => 8192,
    'max_document_kb' => 20480,
];
