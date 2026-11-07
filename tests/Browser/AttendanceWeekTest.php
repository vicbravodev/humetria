<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use Pest\Browser\Playwright\Playwright;

beforeEach(function (): void {
    Playwright::setHost('localhost');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

it('opens «Mi semana» showing nothing of anybody', function (bool $dark, bool $mobile): void {
    visitPage('attendance/week', '/a/acme/week', $dark, $mobile)->assertSee('Mi semana');
})->with([
    'light, desktop' => [false, false],
    'dark, phone' => [true, true],
]);
