<?php

declare(strict_types=1);

return [
    // The articles: one Markdown file each, named after its slug. Read as code (CI runs when one changes); see docs/blog/.
    'path' => base_path('content/blog'),

    // Articles per page of the index.
    'per_page' => 12,
];
