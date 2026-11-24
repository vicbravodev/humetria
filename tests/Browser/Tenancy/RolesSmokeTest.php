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

it('opens the editor of a new role', function (bool $dark, bool $mobile): void {
    signInToPanel();

    visitPage('tenancy/role-editor', '/admin/acme/roles/create', $dark, $mobile)->assertSee('Rol');
})->with($looks);

it('opens the permissions a person has besides their role', function (bool $dark, bool $mobile): void {
    signInToPanel();
    $member = App\Modules\Tenancy\Models\TenantMembership::factory()
        ->for(App\Modules\Tenancy\Models\Tenant::query()->where('slug', 'acme')->sole())
        ->for(App\Models\User::factory()->create())
        ->viewer()->create();

    visitPage('tenancy/member-permissions', "/admin/acme/members/{$member->id}/permissions", $dark, $mobile)->assertSee('Permisos adicionales');
})->with($looks);
