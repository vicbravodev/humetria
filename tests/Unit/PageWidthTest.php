<?php

declare(strict_types=1);

/*
 * One width rule for every page (docs/design-system/design-system.md, «Espacio, forma y profundidad»): the frame of the
 * app (`data-slot="page-body"` in AppSidebarLayout) fills up to 1440 px and centers itself, so no page puts its own
 * cap on its container. A form or a reading page narrows to 880 px (960 for a dossier), aligned to the left: that one
 * needs no entry. Anything else is a page outside the frame of the app and is listed below with its reason.
 */

/**
 * A width cap on a page's container: a plain `max-w-*` (not a breakpoint's) of a column's size, 480 px or more, beside
 * `w-full` or `mx-auto`. A narrower one is a piece inside the page (a progress bar, a phone frame), not the page.
 */
function pageWidthCap(string $className): ?string
{
    $tokens = preg_split('/\s+/', trim($className)) ?: [];
    $caps = array_filter(
        $tokens,
        fn (string $token): bool => preg_match('/^max-w-(xl|[2-7]xl)$/', $token) === 1
            || (preg_match('/^max-w-\[(\d+)px\]$/', $token, $size) === 1 && (int) $size[1] >= 480),
    );

    if ($caps === [] || array_intersect(['w-full', 'mx-auto'], $tokens) === []) {
        return null;
    }

    return reset($caps);
}

/** Whether a cap is the reading width: 880 or 960 px, aligned to the left (no `mx-auto`). */
function isReadingWidth(string $className): bool
{
    $tokens = preg_split('/\s+/', trim($className)) ?: [];

    return array_intersect(['max-w-[880px]', 'max-w-[960px]'], $tokens) !== [] && ! in_array('mx-auto', $tokens, true);
}

/** Pages that keep their own width, each with its reason: all but one render outside the frame of the app. */
const PAGES_WITH_THEIR_OWN_WIDTH = [
    // Inside the frame, the NOM-035 report is the document RH prints and presents: it keeps the width of its sheet
    // (1120 px, the same as its bar and its shared link), aligned to the left like every page.
    'results/report.tsx' => 'printed report, aligned to the left',
    // The NOM-035 survey: SurveyShell, one column a person reads on a phone.
    'campaigns/survey.tsx' => 'survey on a phone',
    // The person's portal (PortalShell), opened with their own link: one column for a phone.
    'disclosure/portal-policy.tsx' => 'portal',
    'disclosure-public/centers.tsx' => 'public page of the workers',
    'disclosure-public/center.tsx' => 'public page of the workers',
    'engagement/portal-climate.tsx' => 'portal',
    'engagement/portal-feedback.tsx' => 'portal',
    'leave/portal.tsx' => 'portal',
    'leave/portal-team.tsx' => 'portal',
    'leave/portal-statement.tsx' => 'portal: a sheet of the statement',
    'organization/portal-team.tsx' => 'portal',
    'seating/portal.tsx' => 'portal',
    'telework/portal-document.tsx' => 'portal',
    'telework/portal-notices.tsx' => 'portal',
    'results/portal-support.tsx' => 'portal: the support message, on a phone',
    // The public channel for complaints: no account, one column.
    'complaints-public/form.tsx' => 'public channel',
    'complaints-public/follow-up.tsx' => 'public channel',
    'complaints-public/unavailable.tsx' => 'public channel',
    // A report opened from a temporary link: a document centered on its own page, also printed.
    'results/shared-report.tsx' => 'shared report',
    'compensation/shared-report.tsx' => 'shared report',
    // A first-time configuration or a document written with questions: SetupFrame, centered on its own page.
    'telework/applies.tsx' => 'SetupFrame: one question at a time',
    'tenancy/applies.tsx' => 'SetupFrame',
    'tenancy/onboarding.tsx' => 'SetupFrame',
    'telework/write.tsx' => 'SetupFrame: the questions and the text beside them',
    'disclosure/policy-write.tsx' => 'SetupFrame: the questions and the text beside them',
];

it('recognises a page width cap however its classes are ordered', function (string $className, ?string $cap): void {
    expect(pageWidthCap($className))->toBe($cap);
})->with([
    ['mx-auto flex w-full max-w-[1240px] flex-col gap-8', 'max-w-[1240px]'],
    ['flex w-full max-w-[880px] flex-col', 'max-w-[880px]'],
    ['mx-auto max-w-3xl px-4', 'max-w-3xl'],
    ['flex flex-col gap-8 px-4 py-6 md:px-10', null],
    // A header or a paragraph that limits its own line, not the page.
    ['flex max-w-[760px] flex-col gap-2', null],
    // A field narrowed from a breakpoint on.
    ['w-full sm:max-w-[400px]', null],
    ['w-full max-w-none', null],
    // A piece inside the page.
    ['flex w-full max-w-[240px] flex-col gap-2', null],
    ['mx-auto flex w-full max-w-md flex-col', null],
]);

it('takes 880 or 960 px aligned to the left as the reading width, and nothing else', function (string $className, bool $reading): void {
    expect(isReadingWidth($className))->toBe($reading);
})->with([
    ['flex w-full max-w-[880px] flex-col', true],
    ['flex w-full max-w-[960px] flex-col', true],
    ['mx-auto flex w-full max-w-[880px] flex-col', false],
    ['flex w-full max-w-[1240px] flex-col', false],
]);

it('puts no width cap on a page of the app beyond the reading width', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js/pages/';
    $found = [];

    foreach (frontendSourceFiles('pages') as $file) {
        $page = str_replace($root, '', (string) $file);

        if (array_key_exists($page, PAGES_WITH_THEIR_OWN_WIDTH) || ! str_ends_with($page, '.tsx')) {
            continue;
        }

        preg_match_all('/className="([^"]*)"/', (string) file_get_contents((string) $file), $matches);

        foreach ($matches[1] as $className) {
            $cap = pageWidthCap($className);

            if ($cap !== null && ! isReadingWidth($className)) {
                $found[] = "{$page}: {$cap}".(str_contains($className, 'mx-auto') ? ' mx-auto' : '');
            }
        }
    }

    sort($found);

    expect($found)->toBe([]);
});

it('lists only pages that still exist', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js/pages/';

    expect(array_filter(array_keys(PAGES_WITH_THEIR_OWN_WIDTH), fn (string $page): bool => ! is_file($root.$page)))->toBe([]);
});
