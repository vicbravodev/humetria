<?php

declare(strict_types=1);

/*
 * PostHog starts from the landing's document, written by Blade (App\Http\Analytics). Inertia moves between pages
 * without loading a document, so a <Link> out of the landing would keep PostHog and its CSP alive on another page (the
 * login, /admin), and a <Link> into the landing would show it without ever loading it (the visit would not be measured).
 * Static checks over the .tsx files, no tool of their own.
 */

const LANDING_LOGO = 'components/landing/landing-header.tsx';

/** @return list<string> every opening <Link ...> tag under resources/js, as "path: tag" */
function linkTags(string $under = ''): array
{
    $root = realpath(__DIR__.'/../../resources/js');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$under, FilesystemIterator::SKIP_DOTS));
    $tags = [];

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.tsx') || str_contains((string) $file, '/routes/')) {
            continue;
        }

        preg_match_all('/<Link\b(?:[^>{]|\{(?:[^{}]|\{[^{}]*\})*\})*>/s', (string) file_get_contents((string) $file), $matches);

        foreach ($matches[0] as $tag) {
            $tags[] = substr((string) $file, strlen($root) + 1).': '.preg_replace('/\s+/', ' ', $tag);
        }
    }

    return $tags;
}

it('links to the landing with a plain anchor everywhere, except the logo of its own header', function (): void {
    $offenders = array_values(array_filter(
        linkTags(),
        fn (string $tag): bool => preg_match('/href=\{home\(|href="\/"/', $tag) === 1 && ! str_starts_with($tag, LANDING_LOGO.':'),
    ));

    expect($offenders)->toBe([]);
});

it('keeps the logo of the landing header, the one <Link> to the landing, as it is: same document', function (): void {
    $logos = array_filter(linkTags(), fn (string $tag): bool => str_starts_with($tag, LANDING_LOGO.':') && str_contains($tag, 'href={home('));

    expect($logos)->toHaveCount(1);
});

it('leaves the landing and the privacy notice with plain anchors, never an Inertia <Link> to the panel', function (): void {
    $offenders = array_values(array_filter(
        linkTags(),
        fn (string $tag): bool => (str_contains($tag, 'components/landing/') || str_contains($tag, 'pages/landing') || str_contains($tag, 'pages/privacy-notice'))
            && str_contains($tag, 'href={dashboard'),
    ));

    expect($offenders)->toBe([]);
});
