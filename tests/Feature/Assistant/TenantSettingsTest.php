<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Assistant\Actions\SetTenantAiSettings;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;

mutates(SetTenantAiSettings::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-18 18:00:00');
    config(['humi.monthly_token_budget' => 2_000_000]);
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
});

function settingsUrl(Tenant|string $tenant): string
{
    return '/admin/companies/'.($tenant instanceof Tenant ? $tenant->id : $tenant).'/assistant';
}

it('lives with the other actions on a company in the console', function (): void {
    expect(route('companies.assistant.show', 'abc', absolute: false))->toBe('/admin/companies/abc/assistant')
        ->and(route('companies.assistant.update', 'abc', absolute: false))->toBe('/admin/companies/abc/assistant');
});

it('shows a company that never configured Humi as on, with the default budget and what it used this month', function (): void {
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'input_tokens' => 700, 'output_tokens' => 300]);
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'input_tokens' => 5000, 'output_tokens' => 0, 'created_at' => '2026-10-02 12:00:00']);
    AiUsage::factory()->create(['tenant_id' => $this->b->id, 'input_tokens' => 9999, 'output_tokens' => 0]);

    $this->actingAs(superadmin())->getJson(settingsUrl($this->a))->assertOk()->assertExactJson([
        'enabled' => true,
        'monthly_token_budget' => null,
        'default_monthly_token_budget' => 2_000_000,
        'used_tokens' => 1000,
    ]);
});

it('turns Humi off for a company and sets its budget, and only for that company', function (): void {
    $this->actingAs(superadmin())->putJson(settingsUrl($this->a), ['enabled' => false, 'monthly_token_budget' => 500])->assertNoContent();

    $this->actingAs(superadmin())->getJson(settingsUrl($this->a))->assertJson(['enabled' => false, 'monthly_token_budget' => 500]);
    $this->actingAs(superadmin())->getJson(settingsUrl($this->b))->assertJson(['enabled' => true, 'monthly_token_budget' => null]);
    expect(TenantAiSettings::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('changes the settings of a company again, and goes back to the default budget with none', function (): void {
    $this->actingAs(superadmin())->putJson(settingsUrl($this->a), ['enabled' => false, 'monthly_token_budget' => 500])->assertNoContent();
    $this->actingAs(superadmin())->putJson(settingsUrl($this->a), ['enabled' => true, 'monthly_token_budget' => null])->assertNoContent();

    $this->actingAs(superadmin())->getJson(settingsUrl($this->a))->assertJson(['enabled' => true, 'monthly_token_budget' => null]);
    expect(TenantAiSettings::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('logs the change and records it in the audit trail, with the figures and never what anyone asked Humi', function (): void {
    Log::spy();

    $this->actingAs(superadmin())->putJson(settingsUrl($this->a), ['enabled' => false, 'monthly_token_budget' => 500])->assertNoContent();

    Log::shouldHaveReceived('info')->with('assistant.settings_changed', ['tenant_id' => $this->a->id, 'enabled' => false, 'monthly_token_budget' => 500])->once();
    $entry = AuditEntry::query()->where('event', 'assistant.settings_changed')->sole();

    expect($entry->tenant_id)->toBe($this->a->id)
        ->and($entry->metadata)->toEqual(['enabled' => false, 'monthly_token_budget' => 500]);
});

it('validates what the superadmin sends', function (array $body, string $field): void {
    $this->actingAs(superadmin())->putJson(settingsUrl($this->a), $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no switch' => [['monthly_token_budget' => 10], 'enabled'],
    'a switch that is no boolean' => [['enabled' => 'quizá'], 'enabled'],
    'a negative budget' => [['enabled' => true, 'monthly_token_budget' => -1], 'monthly_token_budget'],
    'a budget that is no number' => [['enabled' => true, 'monthly_token_budget' => 'mucho'], 'monthly_token_budget'],
    'a budget with decimals' => [['enabled' => true, 'monthly_token_budget' => 10.5], 'monthly_token_budget'],
]);

it('answers 404 to anyone who is not the superadmin, even the owner of the company', function (): void {
    $owner = ownerOf($this->a);
    $hr = memberOf($this->a, TenantRole::Hr);

    foreach ([$owner, $hr] as $person) {
        $this->actingAs($person)->getJson(settingsUrl($this->a))->assertNotFound();
        $this->actingAs($person)->putJson(settingsUrl($this->a), ['enabled' => false])->assertNotFound();
    }

    expect(TenantAiSettings::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('asks a guest to sign in', function (): void {
    $this->getJson(settingsUrl($this->a))->assertUnauthorized();
});

it('answers 404 to a company that does not exist', function (): void {
    $this->actingAs(superadmin())->getJson(settingsUrl((string) Str::uuid()))->assertNotFound();
    $this->actingAs(superadmin())->putJson(settingsUrl((string) Str::uuid()), ['enabled' => false])->assertNotFound();
    $this->actingAs(superadmin())->getJson(settingsUrl('no-es-un-id'))->assertNotFound();
});

it('answers the form of the console like its other actions: back to the list of companies with a toast', function (): void {
    $this->actingAs(superadmin())
        ->put(settingsUrl($this->a), ['enabled' => '0', 'monthly_token_budget' => ''])
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Humi actualizada para la empresa.']]);

    $settings = TenantAiSettings::query()->withoutGlobalScopes()->sole();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->monthly_token_budget)->toBeNull();
});
