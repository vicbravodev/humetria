<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

mutates(AssignRequestContext::class, AssignRequestId::class);

it('assigns a request id to every web request and returns it in a header', function (): void {
    $response = $this->get(route('home'));

    $requestId = $response->headers->get('X-Request-Id');

    expect(Str::isUuid($requestId))->toBeTrue()
        ->and(Context::get('request_id'))->toBe($requestId);
});

it('generates its own request id instead of trusting the client header', function (): void {
    $response = $this->get(route('home'), ['X-Request-Id' => 'attacker-controlled']);

    expect($response->headers->get('X-Request-Id'))->not->toBe('attacker-controlled');
});

it('adds the authenticated user id to the log context', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'));

    expect(Context::get('user_id'))->toBe($user->id);
});

it('does not add a user id for guests', function (): void {
    $this->get(route('home'));

    expect(Context::has('user_id'))->toBeFalse();
});

it('puts the impersonator id on the log context while impersonating', function (): void {
    $superadmin = superadmin();
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->withSession([Impersonation::SESSION_KEY => $superadmin->id, Impersonation::IMPERSONATED_KEY => $owner->id])
        ->get(route('dashboard'));

    expect(Context::get('impersonator_id'))->toBe($superadmin->id);
});

it('does not add an impersonator id outside an impersonation', function (): void {
    $this->actingAs(User::factory()->create())->get(route('dashboard'));

    expect(Context::has('impersonator_id'))->toBeFalse();
});
