<?php

declare(strict_types=1);

return [
    /*
    | Largest file, in kilobytes, that can be uploaded as evidence: photos of a workplace (jpeg or png) and documents (pdf).
    */
    'max_photo_kb' => 8192,
    'max_document_kb' => 20480,

    /*
    | Most photos one submission may carry for each question, and for the whole list.
    */
    'max_photos_per_group' => 10,
];
