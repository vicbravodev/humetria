<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\NominatimPlaceSearch;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

mutates(NominatimPlaceSearch::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

function nominatimAnswers(): void
{
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
        ['display_name' => 'Avenida Industrial 45, Querétaro, México', 'lat' => '20.5888184', 'lon' => '-100.3898881'],
        ['display_name' => 'Calle Industrial, León, México', 'lat' => '21.1250077', 'lon' => '-101.6859605'],
    ])]);
}

it('finds the places that match an address, so RH can drop a zone there', function (): void {
    nominatimAnswers();

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => '  Avenida Industrial 45 ']))
        ->assertOk()
        ->assertExactJson(['places' => [
            ['name' => 'Avenida Industrial 45, Querétaro, México', 'latitude' => 20.5888184, 'longitude' => -100.3898881],
            ['name' => 'Calle Industrial, León, México', 'latitude' => 21.1250077, 'longitude' => -101.6859605],
        ]]);

    // Nominatim's policy: an identifying User-Agent, results in Spanish, in Mexico first, a few at a time.
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search?')
        && $request['q'] === 'Avenida Industrial 45'
        && $request['format'] === 'jsonv2'
        && $request['countrycodes'] === 'mx'
        && $request['limit'] === 5
        && $request['accept-language'] === 'es'
        && $request->hasHeader('User-Agent', 'Humetria/1.0 (+'.config('app.url').')'));
});

it('asks Nominatim once for the same address, whoever repeats it', function (): void {
    nominatimAnswers();
    $user = memberOf($this->tenant, TenantRole::Owner);

    $this->actingAs($user)->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'Avenida Industrial 45']))->assertOk();
    $this->actingAs($user)->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'avenida industrial 45']))->assertOk()->assertJsonCount(2, 'places');

    Http::assertSentCount(1);
});

it('answers an empty list when nothing matches', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'zzzz qqqq']))
        ->assertOk()
        ->assertExactJson(['places' => []]);
});

it('says the search is down when Nominatim fails, so RH can touch the map instead', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'Avenida Industrial 45']))
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'No pudimos buscar la dirección. Toca el mapa o usa tu ubicación.']);
});

it('asks for at least three letters and at most 120', function (string $q): void {
    Http::fake();

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => $q]))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('address')
        ->assertJsonPath('errors.address.0', fn (string $message): bool => str_contains($message, 'dirección'));

    Http::assertNothingSent();
})->with(['empty' => [''], 'two letters' => ['ab'], 'too long' => [str_repeat('a', 121)]]);

it('lets only who may change zones search', function (TenantRole $role): void {
    Http::fake();

    $this->actingAs(memberOf($this->tenant, $role))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'Avenida Industrial 45']))
        ->assertForbidden();

    Http::assertNothingSent();
})->with(['viewer' => [TenantRole::Viewer], 'committee' => [TenantRole::Committee]]);

it('leaves out what Nominatim answers without a name or numeric coordinates', function (): void {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
        ['display_name' => 'Sin coordenadas'],
        ['display_name' => 'Latitud rota', 'lat' => 'norte', 'lon' => '-100.1'],
        ['lat' => '20.1', 'lon' => '-100.1'],
        'una cadena',
        ['display_name' => 'Planta Querétaro', 'lat' => '20.5', 'lon' => '-100.4'],
    ])]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->getJson(route('tenant.attendance.places', ['tenant' => $this->tenant, 'address' => 'Planta Querétaro']))
        ->assertExactJson(['places' => [['name' => 'Planta Querétaro', 'latitude' => 20.5, 'longitude' => -100.4]]]);
});
