<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Data\ChannelSetting;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/*
 * A session gets its own room in the demo: a work center of its own, the group minimum at 1 (so a handful of people sees
 * results) and WhatsApp on with the words in force, as the owner would leave it on «Canales de comunicación».
 */

/** @return array<string, ChannelSetting> the demo's channels by name */
function demoChannels(string $tenantId): array
{
    $settings = [];

    foreach (resolve(CompanyChannels::class)->settings($tenantId) as $setting) {
        $settings[$setting->channel] = $setting;
    }

    return $settings;
}

it('stages a new work center, minimum 1 and WhatsApp on', function (): void {
    config(['whatsapp.consent.version' => '2026-11']);
    $demo = Tenant::factory()->demo()->create(['min_group' => 5]);

    $centerId = resolve(DemoWorkspace::class)->stageSession(null);

    $center = WorkCenter::query()->withoutGlobalScopes()->findOrFail($centerId);
    $channels = demoChannels($demo->id);
    expect($center->tenant_id)->toBe($demo->id)
        ->and($center->name)->toBe('Sala de demostración')
        ->and($demo->refresh()->min_group)->toBe(1)
        ->and($channels['whatsapp']->enabled)->toBeTrue()
        ->and($channels['whatsapp']->consentVersion)->toBe('2026-11')
        ->and($channels['mail']->enabled)->toBeTrue()
        ->and($channels['mail']->preference)->toBe(1);
});

it('names the center after the client', function (): void {
    Tenant::factory()->demo()->create();

    $centerId = resolve(DemoWorkspace::class)->stageSession('Grupo Ejemplo');

    expect(WorkCenter::query()->withoutGlobalScopes()->findOrFail($centerId)->name)->toBe('Sala · Grupo Ejemplo');
});

it('refuses to stage a session when there is no demo company', function (): void {
    Tenant::factory()->create();

    expect(fn () => resolve(DemoWorkspace::class)->stageSession(null))->toThrow(LogicException::class)
        ->and(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
});
