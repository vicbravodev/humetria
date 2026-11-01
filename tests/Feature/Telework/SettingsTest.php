<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Telework\Actions\UpdateTeleworkSettings;
use App\Modules\Telework\Http\Controllers\TeleworkSettingsController;
use App\Modules\Telework\Models\Consent;
use App\Modules\Telework\Models\TeleworkSettings;
use App\Modules\Telework\Support\Settings;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Settings::class, UpdateTeleworkSettings::class, TeleworkSettingsController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @return array<string, mixed> */
function settingsForm(array $overrides = []): array
{
    return ['period_months' => 12, 'consent_text' => resolve(Nom037Checklist::class)->baseConsent(), ...$overrides];
}

it('defaults to 12 months and the base consent', function (): void {
    workIn($this->tenant);
    $settings = resolve(Settings::class);

    expect($settings->periodMonths())->toBe(12)
        ->and($settings->currentConsent()->version)->toBe(1)
        ->and($settings->currentConsent()->text)->toBe(resolve(Nom037Checklist::class)->baseConsent())
        ->and(Str::isUuid($settings->currentConsent()->id, 4))->toBeTrue()
        ->and(TeleworkSettings::query()->count())->toBe(1)
        ->and(Consent::query()->count())->toBe(1);
});

it('shows the settings with the current consent version', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.telework.settings.edit', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/settings')
            ->where('periodMonths', 12)
            ->where('presets', [3, 6, 12, 24])
            ->where('consent.version', 1)
            ->where('consent.text', resolve(Nom037Checklist::class)->baseConsent()));
});

it('accepts the presets and any custom period from 1 to 60 months', function (int $months): void {
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => $months]))
        ->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.settings.edit', $this->tenant));

    workIn($this->tenant);
    expect(resolve(Settings::class)->periodMonths())->toBe($months);
})->with([3, 6, 12, 24, 1, 60, 7]);

it('rejects a period outside 1 to 60 months or that is not a whole number', function (mixed $months): void {
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => $months]))
        ->assertSessionHasErrors('period_months');

    workIn($this->tenant);
    expect(resolve(Settings::class)->periodMonths())->toBe(12);
})->with([0, 61, 'x', null, 2.5, -3]);

it('names the fields in Spanish', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => 61]))
        ->assertSessionHasErrors(['period_months' => 'El campo periodicidad no debe ser mayor que 60.']);
});

it('rejects a period outside 1 to 60 months in the database', function (int $months): void {
    TeleworkSettings::query()->create(['tenant_id' => $this->tenant->id, 'review_period_months' => $months]);
})->with([0, 61])->throws(QueryException::class, 'telework_settings_period_range');

it('versions the consent text when it changes and keeps the old one', function (): void {
    $base = resolve(Nom037Checklist::class)->baseConsent();
    $put = fn (string $text) => $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['consent_text' => $text]))->assertSessionHasNoErrors();

    $put($base);
    $put('Texto propio de la empresa.');
    $put('Texto propio de la empresa.');

    workIn($this->tenant);
    $versions = Consent::query()->orderBy('version')->get();

    expect($versions->pluck('version')->all())->toBe([1, 2])
        ->and($versions->pluck('text')->all())->toBe([$base, 'Texto propio de la empresa.'])
        ->and(resolve(Settings::class)->currentConsent()->version)->toBe(2)
        ->and(resolve(Settings::class)->currentConsent()->text)->toBe('Texto propio de la empresa.');
});

it('keeps the current consent when no text comes', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), ['period_months' => 6])->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(Consent::query()->count())->toBe(1)->and(resolve(Settings::class)->periodMonths())->toBe(6);
});

it('rejects a duplicate consent version in the database', function (): void {
    workIn($this->tenant);
    resolve(Settings::class)->currentConsent();

    Consent::query()->create(['tenant_id' => $this->tenant->id, 'version' => 1, 'text' => 'otro']);
})->throws(QueryException::class, 'telework_consents_version_unique');

it('lets only manage-telework change settings', function (TenantRole $role, int $status): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.telework.settings.edit', $this->tenant))->assertStatus($status);
    $this->actingAs($user)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => 3]))->assertStatus($status === 200 ? 302 : $status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('sends a guest to sign in', function (): void {
    $this->get(route('tenant.telework.settings.edit', $this->tenant))->assertRedirect(route('login'));
});

it('records the change in the audit trail without the consent text', function (): void {
    Log::spy();

    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => 24, 'consent_text' => 'Texto secreto de prueba.']))->assertSessionHasNoErrors();

    $entry = AuditEntry::query()->where('event', 'telework.settings_changed')->sole();

    expect($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->actor_id)->toBe($this->hr->id)
        ->and($entry->metadata)->toEqual(['period_months' => 24, 'previous_period_months' => 12, 'consent_version' => 2])
        ->and(json_encode($entry->metadata))->not->toContain('secreto');
    Log::shouldHaveReceived('info')->with('telework.settings_changed', ['period_months' => 24, 'previous_period_months' => 12, 'consent_version' => 2])->once();
});

it('records nothing when nothing changed', function (): void {
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm())->assertSessionHasNoErrors();

    expect(AuditEntry::query()->where('event', 'telework.settings_changed')->count())->toBe(0);
});

it('keeps each company to its own settings and consents', function (): void {
    $other = Tenant::factory()->create();
    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm(['period_months' => 3, 'consent_text' => 'Texto de A']))->assertSessionHasNoErrors();

    workIn($other);
    expect(resolve(Settings::class)->periodMonths())->toBe(12)
        ->and(resolve(Settings::class)->currentConsent()->text)->toBe(resolve(Nom037Checklist::class)->baseConsent())
        ->and(TeleworkSettings::query()->count())->toBe(1)
        ->and(Consent::query()->count())->toBe(1);

    // Another company's member cannot reach it through the URL: not a member, so the company does not exist for them.
    $this->actingAs(memberOf($other, TenantRole::Owner))->get(route('tenant.telework.settings.edit', $this->tenant))->assertNotFound();
});

it('tells the page the longest period allowed, and confirms the change in a toast', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.telework.settings.edit', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('maxMonths', 60));

    $this->actingAs($this->hr)->put(route('tenant.telework.settings.update', $this->tenant), settingsForm())
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Ajustes de NOM-037 guardados.']]);
});
