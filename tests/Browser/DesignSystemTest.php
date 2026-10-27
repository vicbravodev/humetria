<?php

declare(strict_types=1);

use App\Models\User;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('renders guest pages accessibly', function (string $path, bool $dark, bool $mobile): void {
    // The logo renders a light and a dark <img>; only the one matching the theme is visible.
    $logo = 'img[alt="Humetría"]:visible';

    $page = $mobile ? visit($path)->on()->mobile() : visit($path);
    $page = $dark ? $page->inDarkMode() : $page->inLightMode();

    $page->assertVisible($logo)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);
})->with(['/login', '/register'])->with($looks);

it('renders the dashboard accessibly with the logo in the sidebar', function (bool $dark, bool $mobile): void {
    $logo = 'img[alt="Humetría"]:visible';

    $this->actingAs(User::factory()->create());

    $page = $mobile ? visit('/dashboard')->on()->mobile() : visit('/dashboard');
    $page = $dark ? $page->inDarkMode() : $page->inLightMode();

    if ($mobile) {
        // On mobile the sidebar is an off-canvas sheet: open it and let it finish sliding in.
        $page->click('[data-sidebar="trigger"]')
            ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => document.querySelector(\'[data-mobile="true"][data-state="open"]\') !== null)');
    }

    $page->assertVisible($logo)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);
})->with($looks);
