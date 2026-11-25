<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyBranding;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyBrandReader;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CompanyBrandReader::class);

/** @return array{string, string} the report as HR sees it, and the link that opens it with no account */
function brandedReportOf(Tenant $tenant): array
{
    $center = WorkCenter::factory()->for($tenant)->create();
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $token = Str::random(64);
    ReportShare::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'token_hash' => ReportShare::hashToken($token),
        'expires_at' => now()->addHour(),
        'created_by' => memberOf($tenant, TenantRole::Owner)->id,
    ]);

    return [route('tenant.campaigns.report', [$tenant, $campaign->id]), route('report.shared', $token)];
}

it('knows no brand outside a company', function (): void {
    expect(resolve(CompanyBranding::class)->current())->toBeNull();
});

it('gives the brand of the company the request works in: its name, its initials, its logo and its accent', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo', 'logo_path' => 'logos/x/a.png', 'logo_width' => 100, 'logo_height' => 40, 'brand_color' => '#e91e63']);
    resolve(Companies::class)->enter($tenant->id);

    $brand = resolve(CompanyBranding::class)->current();

    expect($brand?->name)->toBe('Autopartes Demo')
        ->and($brand->initials)->toBe('AD')
        ->and($brand->logo?->width)->toBe(100)
        ->and($brand->logo?->url)->toContain('logos/x/a.png')
        ->and($brand->css)->toContain('--primary:');
});

it('has no accent css while the company keeps the accent of Humetría', function (): void {
    resolve(Companies::class)->enter(Tenant::factory()->create()->id);

    expect(resolve(CompanyBranding::class)->current()?->css)->toBeNull();
});

it('reads the brand without a query of its own', function (): void {
    $tenant = Tenant::factory()->create();
    resolve(Companies::class)->enter($tenant->id);

    $this->expectsDatabaseQueryCount(0);

    resolve(CompanyBranding::class)->current();
});

it('shares the brand with the pages of the panel and with a report opened by link, ', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'brand_color' => '#e91e63']);
    [$report, $shared] = brandedReportOf($tenant);

    $this->actingAs(memberOf($tenant, TenantRole::Hr))->get($report)
        ->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Acme')->where('brand.initials', 'A')->has('brand.css'));

    auth()->logout();

    $this->get($shared)
        ->assertInertia(fn (Assert $page) => $page->where('brand.name', 'Acme'));
});

it('shares no brand on a page that belongs to no company', function (): void {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
});

it('paints the page with the accent of the company, after the stylesheet so it wins, and leaves Humetría\'s alone without one', function (): void {
    $branded = Tenant::factory()->create(['brand_color' => '#e91e63']);
    [, $shared] = brandedReportOf($branded);

    $html = $this->get($shared)->getContent();

    expect(preg_match('/<style nonce="[^"]+">:root\{--primary:#e01d5f[^<]*\.dark\{[^<]*<\/style>/', (string) $html, $style, PREG_OFFSET_CAPTURE))->toBe(1)
        ->and($style[0][1])->toBeGreaterThan((int) strpos((string) $html, 'apple-touch-icon'));
});

it('adds no accent style for a company that keeps the accent of Humetría', function (): void {
    [, $shared] = brandedReportOf(Tenant::factory()->create());

    expect((string) $this->get($shared)->getContent())->not->toMatch('/<style nonce="[^"]+">:root\{--primary/');
});

it('shares the brand only of the company the request itself resolved, never one entered before it', function (): void {
    resolve(Companies::class)->enter(Tenant::factory()->create(['trade_name' => 'Acme'])->id);

    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('brand', null));
});
