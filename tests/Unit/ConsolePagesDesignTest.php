<?php

declare(strict_types=1);

/**
 * The superadmin console is the panel's frame without a company (docs/design-system/design-system.md, «Consola de superadmin»):
 * its pages open with `PageHeader` and sit in the page container of «Composición y espacio», 32 px between blocks.
 */
const CONSOLE_PAGES = [
    'announcements/index', 'billing/account', 'billing/accounts', 'billing/plans', 'demos/index', 'operations/health',
    'operations/messaging', 'tenancy/audit', 'tenancy/company', 'tenancy/index', 'tenancy/overview', 'tenancy/users',
];

const PAGE_CONTAINER = 'flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16';

it('opens every page of the console with PageHeader, inside the page container of the design system', function (string $page): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2)."/resources/js/pages/{$page}.tsx");
    // Its own header component counts when that component is PageHeader underneath.
    preg_match_all("~from '@/components/([\\w/-]+-header)'~", $source, $headers);
    $header = str_contains($source, 'PageHeader') || collect($headers[1])->contains(
        fn (string $path): bool => str_contains((string) file_get_contents(dirname(__DIR__, 2)."/resources/js/components/{$path}.tsx"), '<PageHeader'),
    );

    expect($source)->toContain('className="'.PAGE_CONTAINER.'"')
        ->and($header)->toBeTrue();
})->with(CONSOLE_PAGES);
