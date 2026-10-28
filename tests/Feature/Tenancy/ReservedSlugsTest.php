<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Requests\TenantRules;
use Illuminate\Support\Facades\Route;

it('reserves every first segment the app uses under /admin', function (): void {
    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(fn (Illuminate\Routing\Route $route): array => explode('/', $route->uri()))
        ->filter(fn (array $parts): bool => $parts[0] === 'admin' && isset($parts[1]) && ! str_starts_with($parts[1], '{'))
        ->map(fn (array $parts): string => $parts[1])
        ->unique()
        ->values();

    expect($segments)->not->toBeEmpty()
        ->and($segments->diff(TenantRules::RESERVED_SLUGS)->values()->all())->toBe([]);
});
