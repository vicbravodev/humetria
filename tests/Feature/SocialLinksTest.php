<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

test('the landing shares no social links while none is configured', function () {
    config(['app.social' => ['linkedin' => null, 'facebook' => '', 'instagram' => null]]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('social', []));
});

test('the landing shares the configured social links, in a fixed order', function () {
    config(['app.social' => [
        'linkedin' => 'https://www.linkedin.com/company/humetria',
        'facebook' => 'https://www.facebook.com/humetria',
        'instagram' => 'https://www.instagram.com/humetria',
    ]]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('social', [
        ['network' => 'linkedin', 'url' => 'https://www.linkedin.com/company/humetria'],
        ['network' => 'facebook', 'url' => 'https://www.facebook.com/humetria'],
        ['network' => 'instagram', 'url' => 'https://www.instagram.com/humetria'],
    ]));
});

test('a link that is not https is never shared', function () {
    config(['app.social' => [
        'linkedin' => 'javascript:alert(1)',
        'facebook' => 'http://www.facebook.com/humetria',
        'instagram' => 'https://www.instagram.com/humetria',
    ]]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('social', [
        ['network' => 'instagram', 'url' => 'https://www.instagram.com/humetria'],
    ]));
});
