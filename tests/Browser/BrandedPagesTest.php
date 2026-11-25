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

it('keeps the accent of a company on its own pages: going from one to another, or back to a page of no company, changes it', function (bool $dark): void {
    $user = App\Models\User::factory()->create();
    foreach ([['Acme', 'acme', '#e91e63'], ['Verde', 'verde', null]] as [$name, $slug, $color]) {
        $tenant = Tenant::factory()->create(['trade_name' => $name, 'slug' => $slug, 'brand_color' => $color]);
        App\Modules\Tenancy\Models\TenantMembership::factory()->for($tenant)->for($user)->create();
    }
    $this->actingAs($user);
    // A visit changes the page without loading it again: the accent follows the visit, not the first load.
    $page = visitPage('tenancy/companies', '/admin', $dark);
    $page->assertScript('() => document.querySelectorAll("#brand-accent").length', 0);

    $page->click('Acme')->wait(1)->assertScript('() => document.querySelectorAll("#brand-accent").length', 1);

    // Back to a page of no company (what a logout lands on): Humetría's accent again, not the company's.
    $page->script('() => history.back()');
    $page->wait(1)->assertScript('() => document.querySelectorAll("#brand-accent").length', 0);

    // From one company to another that has none of its own.
    $page->click('Verde')->wait(1)->assertScript('() => document.querySelectorAll("#brand-accent").length', 0);
})->with([[false], [true]]);
