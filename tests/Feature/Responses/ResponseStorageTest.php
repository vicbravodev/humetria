<?php

declare(strict_types=1);

use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Privacy invariant (CLAUDE.md, criterio C-22): an anonymous answer keeps nothing that leads back to the person — no
 * column, key or index toward an employee, a participant, an invitation or a token, and no moment it was given.
 */
it('keeps anonymous responses with no way back to the person who gave them', function (): void {
    $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'anonymous_responses' ORDER BY column_name"))
        ->pluck('column_name')->all();
    $references = collect(DB::select(<<<'SQL'
        SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
        JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
        WHERE source.relname = 'anonymous_responses' AND c.contype = 'f' ORDER BY referenced
    SQL))->pluck('referenced')->all();

    expect($columns)->toBe(['answers', 'area_id', 'campaign_id', 'id', 'instrument', 'instrument_version', 'tenant_id', 'work_center_id'])
        ->and($references)->toBe(['areas', 'campaigns', 'tenants', 'work_centers']);
});

it('gives an anonymous response a random UUID v4, never one that orders by time', function (): void {
    $response = AnonymousResponse::factory()->create();

    expect($response->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('refuses to change or delete an anonymous response or a Guía I screening', function (string $model, string $operation): void {
    $row = $model::factory()->create();

    $operation === 'update'
        ? DB::table($row->getTable())->where('id', $row->id)->update(['answers' => '{}'])
        : DB::table($row->getTable())->where('id', $row->id)->delete();
})->with([AnonymousResponse::class, TraumaScreening::class])->with(['update', 'delete'])->throws(QueryException::class);

it('stores one Guía I screening per person and campaign', function (): void {
    $screening = TraumaScreening::factory()->create();

    TraumaScreening::factory()->create(['tenant_id' => $screening->tenant_id, 'campaign_id' => $screening->campaign_id, 'employee_id' => $screening->employee_id]);
})->throws(QueryException::class);

it('never shows another company\'s responses', function (): void {
    AnonymousResponse::factory()->create();
    TraumaScreening::factory()->create();
    workIn(Tenant::factory()->create());

    expect(AnonymousResponse::query()->count())->toBe(0)
        ->and(TraumaScreening::query()->count())->toBe(0);
});
