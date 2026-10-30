<?php

declare(strict_types=1);

use App\Modules\Results\Models\ReportDetails;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Company A never reads nor changes company B's report details or report links through a query.
 */
it('reads only the report details and links of the current company', function (string $model): void {
    $mine = $model::factory()->create();
    $model::factory()->create();

    workIn(Tenant::query()->findOrFail($mine->tenant_id));

    expect($model::query()->pluck('id')->all())->toBe([$mine->id]);
})->with([ReportDetails::class, ReportShare::class]);

it('cannot revoke a link nor rewrite a report of another company', function (): void {
    $link = ReportShare::factory()->create();
    $details = ReportDetails::factory()->create(['conclusions' => 'Ajenas']);
    workIn(Tenant::factory()->create());

    expect(ReportShare::query()->whereKey($link->id)->update(['revoked_at' => now()]))->toBe(0)
        ->and(ReportDetails::query()->whereKey($details->id)->update(['conclusions' => 'Robadas']))->toBe(0)
        ->and(ReportShare::query()->withoutGlobalScopes()->findOrFail($link->id)->revoked_at)->toBeNull()
        ->and(ReportDetails::query()->withoutGlobalScopes()->findOrFail($details->id)->conclusions)->toBe('Ajenas');
});
