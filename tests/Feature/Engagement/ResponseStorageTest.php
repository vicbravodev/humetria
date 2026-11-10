<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** An active survey of `$tenant`, inserted straight: these tests are about the storage of the answers. */
function responseStorageSurvey(Tenant $tenant): string
{
    $id = (string) Str::uuid();
    DB::table('engagement_surveys')->insert([
        'id' => $id, 'tenant_id' => $tenant->id, 'kind' => 'climate', 'status' => 'active', 'name' => 'Clima', 'bank_version' => 'v1',
        'items' => '[]', 'closes_on' => '2026-12-01', 'launched_at' => '2026-11-10 12:00:00', 'created_by' => User::factory()->create()->id,
        'created_at' => '2026-11-10 12:00:00', 'updated_at' => '2026-11-10 12:00:00',
    ]);

    return $id;
}

/** @return list<string> */
function responseStorageColumns(string $table): array
{
    return collect(DB::select('SELECT column_name FROM information_schema.columns WHERE table_name = ? ORDER BY column_name', [$table]))->pluck('column_name')->all();
}

/** @return list<string> */
function responseStorageReferences(string $table): array
{
    return collect(DB::select(<<<'SQL'
        SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
        JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
        WHERE source.relname = ? AND c.contype = 'f' ORDER BY referenced
    SQL, [$table]))->pluck('referenced')->all();
}

it('keeps in the response only the segment, the answers and the minutes: no column toward the person, the invitation or the token', function (): void {
    expect(responseStorageColumns('engagement_responses'))->toBe(['answers', 'area_id', 'id', 'manager_group_id', 'minutes', 'survey_id', 'tenant_id', 'tenure_band', 'work_center_id'])
        ->and(responseStorageReferences('engagement_responses'))->toBe(['areas', 'engagement_surveys', 'tenants', 'work_centers']);
});

it('keeps in the comment only the question, the group of the manager and the text, tied to no response', function (): void {
    expect(responseStorageColumns('engagement_comments'))->toBe(['body', 'comment_group_id', 'id', 'item_key', 'survey_id', 'tenant_id'])
        ->and(responseStorageReferences('engagement_comments'))->toBe(['engagement_surveys', 'tenants']);
});

it('has no date, time or timestamp column in either table, since when it was answered would tell who answered', function (): void {
    $timed = DB::table('information_schema.columns')
        ->whereIn('table_name', ['engagement_responses', 'engagement_comments'])
        ->where(fn ($query) => $query->where('data_type', 'like', 'timestamp%')->orWhere('data_type', 'like', 'time%')->orWhere('data_type', 'date'))
        ->count();

    expect($timed)->toBe(0);
});

it('gives each response and each comment a random UUID v4, never one that orders by time', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $survey = responseStorageSurvey($tenant);
    $v4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    Response::factory()->count(20)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey]);
    Comment::factory()->count(20)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey]);

    expect(Response::query()->pluck('id'))->toHaveCount(20)->each->toMatch($v4)
        ->and(Comment::query()->pluck('id'))->toHaveCount(20)->each->toMatch($v4);
});

it('stores the answers as given, the omitted ones as null', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);

    $response = Response::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => responseStorageSurvey($tenant), 'answers' => ['clarity.goals' => 4, 'eng.recommend' => null], 'minutes' => 12]);

    expect($response->refresh()->answers)->toBe(['clarity.goals' => 4, 'eng.recommend' => null])->and($response->minutes)->toBe(12);
});

it('rejects an update, a delete and a truncate of either table, even straight in SQL', function (string $table, string $statement): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $survey = responseStorageSurvey($tenant);
    Response::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey]);
    Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey]);

    expect(fn () => DB::statement(str_replace(':table', $table, $statement)))->toThrow(QueryException::class, "{$table} is insert-only");
})->with(['engagement_responses', 'engagement_comments'])->with(['UPDATE :table SET tenant_id = tenant_id', 'DELETE FROM :table', 'TRUNCATE :table']);

it('does not let the company A read or find the responses and comments of the company B', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    workIn($b);
    $response = Response::factory()->create(['tenant_id' => $b->id, 'survey_id' => responseStorageSurvey($b)]);
    $comment = Comment::factory()->create(['tenant_id' => $b->id, 'survey_id' => $response->survey_id]);

    workIn($a);

    expect(Response::query()->count())->toBe(0)->and(Response::query()->find($response->id))->toBeNull()
        ->and(Comment::query()->count())->toBe(0)->and(Comment::query()->find($comment->id))->toBeNull();
});

it('refuses a response of a survey of another company (composite key)', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $surveyOfB = responseStorageSurvey($b);
    workIn($a);

    expect(fn () => Response::factory()->create(['tenant_id' => $a->id, 'survey_id' => $surveyOfB]))->toThrow(QueryException::class);
});
