<?php

declare(strict_types=1);

use App\Http\PageFrame;

mutates(PageFrame::class);

/**
 * The names and prefixes between the brackets of a constant of resources/js/lib/page-frame.ts, without its comments.
 *
 * @return list<string>
 */
function frontendFrameList(string $constant): array
{
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/page-frame.ts');
    preg_match('/const '.$constant.' = (?:new Set\()?\[(.*?)\]/s', $source, $block);
    $code = preg_replace('#//[^\n]*#', '', $block[1] ?? '') ?? '';
    preg_match_all("/'([^']+)'/", $code, $strings);

    return $strings[1];
}

it('lists the pages without the panel frame exactly as the frontend does', function (): void {
    expect(PageFrame::STANDALONE_NAMES)->toBe(frontendFrameList('STANDALONE_NAMES'))
        ->and(PageFrame::STANDALONE_PREFIXES)->toBe(frontendFrameList('STANDALONE_PREFIXES'))
        ->and(PageFrame::STANDALONE_NAMES)->not->toBeEmpty();
});

it('says which pages carry their own frame', function (string $component, bool $standalone): void {
    expect(PageFrame::isStandalone($component))->toBe($standalone);
})->with([
    ['campaigns/survey', true],
    ['telework/portal', true],
    ['telework/portal-checklist', true],
    ['leave/portal-team', true],
    ['complaints-public/form', true],
    ['error', true],
    ['organization/employees', false],
    ['telework/teleworkers', false],
    ['auth/login', false],
    ['settings/profile', false],
]);

it('names the frame files a page needs, none for a page with its own', function (string $component, array $files): void {
    expect(PageFrame::layouts($component))->toBe($files);
})->with([
    ['campaigns/survey', []],
    ['organization/employees', ['resources/js/layouts/app-layout.tsx']],
    ['auth/login', ['resources/js/layouts/auth-layout.tsx']],
    ['tenancy/accept-invitation', ['resources/js/layouts/auth-layout.tsx']],
    ['settings/profile', ['resources/js/layouts/app-layout.tsx', 'resources/js/layouts/settings/layout.tsx']],
]);
