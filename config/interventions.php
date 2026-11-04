<?php

declare(strict_types=1);

return [
    /*
    | Days ahead the weekly reminder warns about an action that is about to be due (criterio C-36). The norm sets no term:
    | it is ours.
    */
    'due_soon_days' => 14,

    /*
    | Largest file, in kilobytes, that can be uploaded as evidence of a step: photos (jpeg or png) and documents (pdf).
    | Attendance lists and call-ups are the usual evidence (STPS, preguntas frecuentes, 18).
    */
    'max_photo_kb' => 8192,
    'max_document_kb' => 20480,
];
