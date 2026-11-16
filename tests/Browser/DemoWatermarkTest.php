<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Str;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** A closed evaluation of the company at /admin/acme, with a link to its report; returns the evaluation and the token. */
function watermarkedReport(Tenant $tenant): array
{
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->for($tenant)->create()->id]);
    $token = Str::random(64);
    ReportShare::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'token_hash' => ReportShare::hashToken($token),
        'expires_at' => now()->addHour(),
        'created_by' => memberOf($tenant, TenantRole::Owner)->id,
    ]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return [$campaign, $token];
}

it('marks the reports of the demo company as DEMO, on screen and on paper', function (bool $dark, bool $mobile): void {
    [$campaign, $token] = watermarkedReport(Tenant::factory()->demo()->create(['slug' => 'acme']));

    visitPage('results/report', "/admin/acme/campaigns/{$campaign->id}/report", $dark, $mobile)
        ->assertSeeIn('[data-slot="demo-mark"]', 'DEMO')
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=\"demo-mark\"]')).position", 'fixed');

    auth()->logout();
    visitPage('results/shared-report', "/report/{$token}", $dark, $mobile)
        ->assertSeeIn('[data-slot="demo-mark"]', 'DEMO');
})->with($looks);

it('shows no mark on the reports of another company', function (): void {
    Tenant::factory()->demo()->create();
    [$campaign, $token] = watermarkedReport(Tenant::factory()->create(['slug' => 'acme']));

    visit("/admin/acme/campaigns/{$campaign->id}/report")
        ->assertSee('Diagnóstico de factores de riesgo psicosocial')
        ->assertMissing('[data-slot="demo-mark"]');

    auth()->logout();
    visit("/report/{$token}")
        ->assertSee('Diagnóstico de factores de riesgo psicosocial')
        ->assertMissing('[data-slot="demo-mark"]');
});
