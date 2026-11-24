<?php

declare(strict_types=1);

use App\Modules\Blog\Exceptions\InvalidArticle;
use App\Modules\Blog\Support\ArticleParser;

/*
 * The gate of the blog: every file in content/blog must pass the rules of ArticleParser, so a malformed article fails the
 * CI instead of silently going missing from the site. Reviewing (reviewed_at) is the owner's check; this is the machine's.
 */

/** @return list<string> */
function contentFiles(): array
{
    return glob(dirname(__DIR__, 3).'/content/blog/*') ?: [];
}

it('has only Markdown files in content/blog', function (): void {
    expect(array_filter(contentFiles(), fn (string $file): bool => ! str_ends_with($file, '.md')))->toBe([]);
});

it('passes the rules in every article', function (): void {
    foreach (contentFiles() as $file) {
        try {
            ArticleParser::parse(basename($file, '.md'), (string) file_get_contents($file));
        } catch (InvalidArticle $invalid) {
            $this->fail(basename($file).': '.implode(' ', $invalid->problems));
        }
    }

    expect(true)->toBeTrue();
});

it('does not repeat a title', function (): void {
    $titles = array_map(
        fn (string $file): string => ArticleParser::parse(basename($file, '.md'), (string) file_get_contents($file))->title,
        contentFiles(),
    );

    expect($titles)->toBe(array_values(array_unique($titles)));
});

it('has both covers of every article, at the size they promise', function (): void {
    foreach (contentFiles() as $file) {
        $slug = basename($file, '.md');

        foreach (["public/blog/{$slug}.webp", "public/blog/{$slug}-og.jpg"] as $cover) {
            $size = @getimagesize(dirname(__DIR__, 3).'/'.$cover);

            expect($size)->not->toBeFalse("{$cover} is missing: run `npm run covers:blog` in video/")
                ->and([$size[0] ?? 0, $size[1] ?? 0])->toBe([1200, 630]);
        }
    }
});
