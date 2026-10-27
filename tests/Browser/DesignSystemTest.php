<?php

declare(strict_types=1);

use App\Models\User;

// The logo renders a light and a dark <img>; only the one matching the theme is visible.
const LOGO = 'img[alt="Humetría"]:visible';

it('renders guest pages accessibly in light and dark, desktop and mobile', function (string $path): void {
    foreach ([
        visit($path),
        visit($path)->inDarkMode(),
        visit($path)->on()->mobile(),
        visit($path)->on()->mobile()->inDarkMode(),
    ] as $page) {
        $page->assertVisible(LOGO)
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues(1);
    }
})->with(['/login', '/register']);

it('renders the dashboard accessibly with the logo in the sidebar', function (): void {
    $this->actingAs(User::factory()->create());

    foreach ([visit('/dashboard'), visit('/dashboard')->inDarkMode()] as $page) {
        $page->assertVisible(LOGO)
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues(1);
    }

    // On mobile the sidebar is an off-canvas sheet: open it to see the logo.
    visit('/dashboard')->on()->mobile()
        ->click('[data-sidebar="trigger"]')
        ->assertVisible(LOGO)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);
});
