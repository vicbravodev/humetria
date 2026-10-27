<?php

declare(strict_types=1);

use App\Models\User;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

// The logo renders a light and a dark <img>; only the one matching the theme is visible.
const VISIBLE_LOGO = 'img[alt="Humetría"]:visible';

function assertLogoInsideItsBox(AwaitableWebpage $page): void
{
    $page->assertVisible(VISIBLE_LOGO)
        ->assertScript('() => [...document.querySelectorAll(\'img[alt="Humetría"]\')].filter((img) => img.offsetParent !== null).every((img) => { const logo = img.getBoundingClientRect(); const box = img.parentElement.getBoundingClientRect(); return logo.left >= box.left && logo.right <= box.right && logo.top >= box.top && logo.bottom <= box.bottom; })');
}

it('renders the login page accessibly', function (bool $dark, bool $mobile): void {
    assertLogoInsideItsBox(visitPage('auth/login', '/login', $dark, $mobile));
})->with($looks);

it('renders the register page accessibly', function (bool $dark, bool $mobile): void {
    assertLogoInsideItsBox(visitPage('auth/register', '/register', $dark, $mobile));
})->with($looks);

it('renders the dashboard accessibly with the logo in the sidebar', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/dashboard', $dark, $mobile, reach: function (AwaitableWebpage $page) use ($mobile): void {
        if ($mobile) {
            // On mobile the sidebar is an off-canvas sheet: open it and let it finish sliding in.
            $page->click('[data-sidebar="trigger"]')
                ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => document.querySelector(\'[data-mobile="true"][data-state="open"]\') !== null)');
        }
    })->assertVisible(VISIBLE_LOGO);
})->with($looks);

it('gives the mobile menu button a 44 px touch target', function (bool $dark): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/dashboard', $dark, true)
        ->assertScript('() => { const box = document.querySelector(\'[data-sidebar="trigger"]\').getBoundingClientRect(); return box.width >= 44 && box.height >= 44; }');
})->with(['light' => false, 'dark' => true]);
