<?php

declare(strict_types=1);

use App\Modules\Demos\Actions\ForgetAdClicks;
use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

mutates(ForgetAdClicks::class);

afterEach(fn () => Carbon::setTestNow());

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
