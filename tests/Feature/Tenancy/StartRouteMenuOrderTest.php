<?php

declare(strict_types=1);

use App\Modules\Tenancy\Support\StartRoute;
use Illuminate\Support\Str;

/**
 * StartRoute walks the menu's pages in the menu's order, which the sidebar draws in the browser: both are held to
 * tests/Fixtures/navigation/menu-order.json (the sidebar by tests/js/lib/menu-order.test.ts), so neither moves alone.
 */
it('walks the pages of the menu in the order the sidebar shows them', function (): void {
    $order = json_decode((string) file_get_contents(base_path('tests/Fixtures/navigation/menu-order.json')), true);
    // The sidebar shows one complaints entry: the cases, or the channel for whoever only manages it (right after the cases).
    $menu = array_values(array_diff(StartRoute::MENU, ['tenant.complaints.channel.show']));

    expect(array_map(fn (string $name): string => Str::after(route($name, 'acme', false), '/admin/acme'), $menu))->toBe($order)
        ->and(array_search('tenant.complaints.channel.show', StartRoute::MENU, true))
        ->toBe(array_search('tenant.complaints.index', StartRoute::MENU, true) + 1);
});
