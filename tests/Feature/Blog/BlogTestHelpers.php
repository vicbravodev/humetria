<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** A folder of articles for one test, set as the blog's content path and removed when the test ends. */
function blogFolder(): string
{
    $path = sys_get_temp_dir().'/blog-'.Str::uuid();
    File::makeDirectory($path);
    config(['blog.path' => $path]);

    test()->afterEach(fn () => File::deleteDirectory($path));

    return $path;
}

/** @param  array<string, string>  $front  lines of the front matter that replace the valid ones, by key; '' drops the key */
function writeArticle(string $folder, string $slug, array $front = [], string $words = '', string $extra = ''): void
{
    $lines = [
        'title' => 'title: "Artículo '.$slug.'"',
        'description' => 'description: "'.str_repeat('d', 130).'"',
        'tool' => 'tool: attendance',
        'published_at' => 'published_at: 2026-11-10',
        'reviewed_by' => 'reviewed_by: "Equipo Humetría"',
        'reviewed_at' => 'reviewed_at: 2026-11-11',
        'sources' => "sources:\n  - https://dof.gob.mx/nota_detalle.php?codigo=1",
        ...$front,
    ];
    $body = "## Qué dice la ley\n\n".trim(str_repeat('palabra ', 520))."\n\n".($words === '' ? '' : $words."\n\n")."[Agenda una demo](/#agenda-una-demo)\n\n{$extra}";

    File::put("{$folder}/{$slug}.md", "---\n".implode("\n", array_filter($lines, fn (string $line): bool => $line !== ''))."\n---\n{$body}");
}
