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

/** A closed evaluation of the company at /admin/acme, signed in as its HR; returns the evaluation and a link to its report. */
function brandedReport(Tenant $tenant): array
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

it('dresses the panel and the report with the accent of the company, readable in light and dark', function (bool $dark, bool $mobile): void {
    [$campaign] = brandedReport(Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme', 'brand_color' => '#ffeb3b']));

    // A yellow this light is nudged until it reads: visitPage runs axe on the page.
    visitPage('results/report', "/admin/acme/campaigns/{$campaign->id}/report", $dark, $mobile)
        ->assertScript('() => getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() !== "#0b7a6f"', true)
        ->assertSee('Acme');
})->with($looks);

it('puts the company, not Humetría, at the head of a report opened by link, with Humetría as the seal', function (bool $dark, bool $mobile): void {
    [, $token] = brandedReport(Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme', 'brand_color' => '#e91e63']));
    auth()->logout();

    visitPage('results/shared-report', "/report/{$token}", $dark, $mobile)
        ->assertScript('() => getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() !== "#0b7a6f"', true)
        ->assertSee('Acme')
        ->assertScript('() => document.querySelectorAll("img[src*=\"humetria-horizontal\"]").length', 0)
        ->assertScript('() => document.querySelectorAll("img[src*=\"humetria-sello\"]").length > 0', true);
})->with($looks);
