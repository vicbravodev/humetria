<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see the privacy notice', function () {
    $this->get('/privacy-notice')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('privacy-notice'));
});

test('signed-in users see the privacy notice too', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('privacy-notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('privacy-notice'));
});

test('it carries the legal name and address from the configuration', function () {
    config(['mail.legal.name' => 'Humetría S.A. de C.V.', 'mail.legal.address' => 'Av. Reforma 1, CDMX']);

    $this->get(route('privacy-notice'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('legalName', 'Humetría S.A. de C.V.')
            ->where('legalAddress', 'Av. Reforma 1, CDMX'));
});

test('an unset or empty legal name and address are null', function (?string $value) {
    config(['mail.legal.name' => $value, 'mail.legal.address' => $value]);

    $this->get(route('privacy-notice'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('legalName', null)
            ->where('legalAddress', null));
})->with([null, '', '   ']);

test('search engines may index it in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('privacy-notice'))->assertOk()->assertHeaderMissing('X-Robots-Tag');
});

test('it stays out of search engines outside production', function () {
    $this->get(route('privacy-notice'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
