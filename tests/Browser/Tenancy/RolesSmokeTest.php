<?php

declare(strict_types=1);

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

// Smoke tests of the roles pages while they are placeholders (Task 7a); Task 7b draws them and asserts what they show.
it('opens the roles of the company', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/roles', '/admin/acme/roles', $dark, $mobile)->assertSee('Roles y permisos');
})->with($looks);
