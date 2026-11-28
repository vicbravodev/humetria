<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;

it('finds the companies whose name has what was typed, for the ⌘K of the console', function (): void {
    Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);
    Tenant::factory()->create(['trade_name' => 'Aceros Monterrey']);

    $this->actingAs(superadmin())
        ->getJson(route('companies.options', ['q' => 'textil']))
        ->assertOk()
        ->assertExactJson([['value' => Tenant::query()->where('trade_name', 'Textiles del Norte')->value('id'), 'label' => 'Textiles del Norte']]);
});

it('answers support too, who also opens companies from the console', function (): void {
    Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);

    $this->actingAs(support())
        ->getJson(route('companies.options', ['q' => 'textil']))
        ->assertOk()
        ->assertJsonCount(1);
});

it('answers 404 to anyone outside Humetría staff, like the rest of the console', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('companies.options', ['q' => 'textil']))
        ->assertNotFound();
});
