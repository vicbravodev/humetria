<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('throws on lazy loading so N+1 queries fail the test suite', function (): void {
    User::factory()->count(2)->create();

    User::query()->get()->each(fn (User $user) => $user->passkeys);
})->throws(LazyLoadingViolationException::class);

it('allows relations that were eager loaded', function (): void {
    User::factory()->count(2)->create();

    $passkeys = User::query()->with('passkeys')->get()->flatMap->passkeys;

    expect($passkeys)->toBeEmpty();
});

it('throws when mass assigning attributes that are not fillable', function (): void {
    new User(['name' => 'Ana', 'is_admin' => true]);
})->throws(Illuminate\Database\Eloquent\MassAssignmentException::class);

it('throws when reading an attribute that was not selected', function (): void {
    User::factory()->create();

    User::query()->select('id')->firstOrFail()->email;
})->throws(MissingAttributeException::class);

it('blocks real HTTP requests during tests', function (): void {
    Http::get('https://example.com');
})->throws(StrayRequestException::class);
