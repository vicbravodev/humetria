<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
});

it('opens the screen of Marcar', function (bool $dark, bool $mobile): void {
    visitPage('attendance/punch', '/a/acme', $dark, $mobile)->assertSee('Marcar');
})->with([
    'light, desktop' => [false, false],
    'dark, phone' => [true, true],
]);
