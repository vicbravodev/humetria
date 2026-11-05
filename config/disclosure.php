<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Policy text
    |--------------------------------------------------------------------------
    |
    | The most a content (the policy, the text of a 5.7 block) may hold, in characters, and the most items a section may list.
    | The content is plain text: there is no Markdown or HTML to parse or to inject into a public page.
    |
    */

    'text_max' => 20000,
    'items_max' => 60,

    /*
    |--------------------------------------------------------------------------
    | Lists
    |--------------------------------------------------------------------------
    |
    | How many rows a page of the panel's lists and of the public centers list holds.
    |
    */

    'per_page' => 10,
    'history_per_page' => 5,
    'records_per_page' => 5,

    /*
    |--------------------------------------------------------------------------
    | Evidence files
    |--------------------------------------------------------------------------
    |
    | The heaviest scanned sheet of signatures or PDF of a means, and the heaviest photo of a poster, in kilobytes.
    |
    */

    'max_document_kb' => 20480,
    'max_photo_kb' => 8192,

    /*
    |--------------------------------------------------------------------------
    | The permanent page of the workers (criterio C-55)
    |--------------------------------------------------------------------------
    |
    | The link never expires unless HR sets a day, up to this many months ahead; the panel warns this many days before it
    | does. The page has its own limits: one link is opened by a whole plant that scans the same poster, so the limit per link
    | is generous and the one per address is what stops scraping.
    |
    */

    'link_max_months' => 36,
    'link_expiry_warning_days' => 30,
    'workers_page' => [
        'per_address_per_minute' => (int) env('DISCLOSURE_PAGE_PER_ADDRESS_PER_MINUTE', 60),
        'per_link_per_minute' => (int) env('DISCLOSURE_PAGE_PER_LINK_PER_MINUTE', 1200),
    ],

];
