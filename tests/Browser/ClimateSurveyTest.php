<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

it('opens a draft', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = Survey::factory()->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->firstOrFail()->id, 'name' => 'Clima · noviembre 2026']);

    visitPage('engagement/show', "{$url}/climate/{$survey->id}", $dark, $mobile)
        ->assertSee('Clima · noviembre 2026')
        ->assertSee('Borrador')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);
