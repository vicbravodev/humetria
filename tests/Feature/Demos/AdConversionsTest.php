<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Actions\ExportAdConversions;
use App\Modules\Demos\Actions\ForgetAdClicks;
use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

mutates(ExportAdConversions::class, ForgetAdClicks::class);

afterEach(fn () => Carbon::setTestNow());

function adConversionsCsv(): string
{
    $response = test()->actingAs(superadmin())->get(route('demo-requests.ad-conversions'))->assertOk();

    return (string) $response->streamedContent();
}

it('lives at /admin/demo-requests/ad-conversions', function (): void {
    expect(route('demo-requests.ad-conversions', absolute: false))->toBe('/admin/demo-requests/ad-conversions');
});

it('sends guests to the login and answers 404 to anyone who is not staff', function (): void {
    $this->get(route('demo-requests.ad-conversions'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('demo-requests.ad-conversions'))->assertNotFound();
});

it('lets support download it too, as it reads the console', function (): void {
    $this->actingAs(support())->get(route('demo-requests.ad-conversions'))->assertOk();
});

it('downloads the Google Ads click import with every request that has a gclid, in Mexico City time', function (): void {
    Carbon::setTestNow('2026-11-17 18:30:05');
    DemoRequest::factory()->withAdClick(AdClickType::Gclid, 'Cj0KCQ-primero')->create(['created_at' => '2026-11-10 16:00:00']);
    DemoRequest::factory()->withAdClick(AdClickType::Gclid, 'Cj0KCQ-segundo')->create(['created_at' => '2026-11-17 18:30:05']);

    $response = $this->actingAs(superadmin())->get(route('demo-requests.ad-conversions'))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('conversiones-google-ads-2026-11-17.csv');

    expect((string) $response->streamedContent())->toBe(implode("\n", [
        'Parameters:TimeZone=America/Mexico_City',
        'Google Click ID,Conversion Name,Conversion Time',
        'Cj0KCQ-primero,Humetría · Solicitud de demo,2026-11-10 10:00:00',
        'Cj0KCQ-segundo,Humetría · Solicitud de demo,2026-11-17 12:30:05',
        '',
    ]));
});

it('names the conversion as the configuration says, the name of the action in Google Ads', function (): void {
    config(['services.google_ads.conversion_name' => 'Otra acción']);
    DemoRequest::factory()->withAdClick(AdClickType::Gclid, 'G1')->create();

    expect(adConversionsCsv())->toContain('G1,Otra acción,');
});

it('leaves out requests without a click id, and the ones of iOS (gbraid, wbraid) that the click import does not take', function (): void {
    DemoRequest::factory()->create();
    DemoRequest::factory()->withAdClick(AdClickType::Gbraid, 'B1')->create();
    DemoRequest::factory()->withAdClick(AdClickType::Wbraid, 'W1')->create();

    expect(adConversionsCsv())->toBe("Parameters:TimeZone=America/Mexico_City\nGoogle Click ID,Conversion Name,Conversion Time\n");
});

it('downloads the clicks of iOS (gbraid, wbraid) in a file of their own, one identifier per row', function (): void {
    Carbon::setTestNow('2026-11-17 18:30:05');
    DemoRequest::factory()->withAdClick(AdClickType::Gbraid, 'B1-app')->create(['created_at' => '2026-11-10 16:00:00']);
    DemoRequest::factory()->withAdClick(AdClickType::Wbraid, 'W1-web')->create(['created_at' => '2026-11-12 20:15:00']);
    DemoRequest::factory()->withAdClick(AdClickType::Gclid, 'G1')->create();
    DemoRequest::factory()->create();

    $response = $this->actingAs(superadmin())->get(route('demo-requests.ad-conversions-ios'))->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('conversiones-google-ads-ios-2026-11-17.csv');

    expect((string) $response->streamedContent())->toBe(implode("\n", [
        'Parameters:TimeZone=America/Mexico_City',
        'GBRAID,WBRAID,Conversion Name,Conversion Time',
        'B1-app,,Humetría · Solicitud de demo,2026-11-10 10:00:00',
        ',W1-web,Humetría · Solicitud de demo,2026-11-12 14:15:00',
        '',
    ]));
});

it('answers 404 to anyone who is not staff for the file of iOS too', function (): void {
    $this->actingAs(User::factory()->create())->get(route('demo-requests.ad-conversions-ios'))->assertNotFound();
});

it('carries no name, email, phone or company of whoever asked', function (): void {
    DemoRequest::factory()->withAdClick(AdClickType::Gclid, 'G1')->create([
        'name' => 'Ana Prueba', 'email' => 'ana@empresa.mx', 'whatsapp' => '+525512345678', 'company' => 'Empresa Ejemplo',
    ]);

    expect(adConversionsCsv())->not->toContain('Ana')->not->toContain('ana@')->not->toContain('5512345678')->not->toContain('Empresa');
});

it('forgets every click id of a request older than 90 days, and keeps the rest of it', function (): void {
    Carbon::setTestNow('2026-11-17 12:00:00');
    $old = DemoRequest::factory()->fromCampaign()->withAdClick(AdClickType::Gclid, 'VIEJO')->create(['created_at' => now()->subDays(90)->subSecond()]);
    $edge = DemoRequest::factory()->withAdClick(AdClickType::Gbraid, 'JUSTO')->create(['created_at' => now()->subDays(90)]);

    $this->artisan('demos:forget-ad-clicks')->expectsOutput('Identificadores de clic olvidados: 1')->assertSuccessful();

    expect($old->refresh()->ad_click_id)->toBeNull()
        ->and($old->ad_click_type)->toBeNull()
        ->and($old->utm_source)->toBe('google')
        ->and($edge->refresh()->ad_click_id)->toBe('JUSTO');
});

it('keeps a click id and its type together in the database, and never a blank one', function (array $values): void {
    $demoRequest = DemoRequest::factory()->create();

    DB::table('demo_requests')->where('id', $demoRequest->id)->update($values);
})->with([
    'an id without a type' => [['ad_click_id' => 'G1']],
    'a type without an id' => [['ad_click_type' => 'gclid']],
    'a blank id' => [['ad_click_type' => 'gclid', 'ad_click_id' => '  ']],
    'an unknown type' => [['ad_click_type' => 'fbclid', 'ad_click_id' => 'X1']],
])->throws(QueryException::class);
