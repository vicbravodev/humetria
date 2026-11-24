<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;

it('answers 404, not a database error, to a numeric id too large to exist', function (string $method, string $uri): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(str_contains($uri, 'platform-users') ? support() : ownerOf($tenant));

    $this->call($method, $uri)->assertNotFound();
})->with([
    'member update' => ['PUT', '/admin/acme/members/99999999999999999999'],
    'member remove' => ['DELETE', '/admin/acme/members/99999999999999999999'],
    'platform user reset' => ['POST', '/admin/platform-users/99999999999999999999/password-reset'],
    'platform user support' => ['DELETE', '/admin/platform-users/99999999999999999999/support'],
    'committee member' => ['DELETE', '/admin/acme/telework/centers/1/committee/members/99999999999999999999'],
]);
