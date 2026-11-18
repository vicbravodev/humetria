<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The text of a tab: the title of the page and then the brand ("Empleados - Humetría"). A title that already starts with
 * the brand (the home's) is the whole title, so the brand is the first thing a narrow tab shows. The same rule is in
 * resources/js/lib/document-title.ts, for the titles React sets.
 */
final class DocumentTitle
{
    public static function for(string $title, string $brand): string
    {
        return str_starts_with($title, $brand) ? $title : "{$title} - {$brand}";
    }
}
