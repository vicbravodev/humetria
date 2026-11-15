<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The database guards demo requests on its own, whatever path writes them.
 */
it('stores a demo request built by its factory', function (): void {
    $demoRequest = DemoRequest::factory()->create();

    expect(Str::isUuid($demoRequest->id, 4))->toBeTrue()
        ->and(DemoRequest::query()->count())->toBe(1);
});

it('accepts a valid row inserted directly', function (): void {
    DB::table('demo_requests')->insert([...DemoRequest::factory()->raw(), 'tools' => '["nom_035"]', 'id' => (string) Str::uuid(), 'message' => str_repeat('a', 2000)]);

    expect(DemoRequest::query()->count())->toBe(1);
});

it('rejects a row the form would reject', function (string $column, mixed $value): void {
    $row = [...DemoRequest::factory()->raw(), 'tools' => '["nom_035"]', 'id' => (string) Str::uuid(), $column => $value];

    expect(fn () => DB::table('demo_requests')->insert($row))->toThrow(QueryException::class);
})->with([
    'missing name' => ['name', null],
    'blank name' => ['name', '  '],
    'name too long' => ['name', str_repeat('a', 121)],
    'blank email' => ['email', ''],
    'email too long' => ['email', str_repeat('a', 191)],
    'blank company' => ['company', ''],
    'company too long' => ['company', str_repeat('a', 161)],
    'whatsapp without +52' => ['whatsapp', '5512345678'],
    'whatsapp too short' => ['whatsapp', '+52551234567'],
    'unknown profile' => ['profile', 'employee'],
    'unknown workforce' => ['workforce', '1_50'],
    'message too long' => ['message', str_repeat('a', 2001)],
    'no tools' => ['tools', '[]'],
    'unknown tool' => ['tools', '["nom_036"]'],
    'missing tools' => ['tools', null],
]);

it('rejects a tools value that is not a list as a check violation', function (): void {
    $row = [...DemoRequest::factory()->raw(), 'tools' => '{"a":"nom_035"}', 'id' => (string) Str::uuid()];

    try {
        DB::table('demo_requests')->insert($row);
        $this->fail('The insert should have been rejected.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('23514');
    }
});

it('keeps the tools a request asked about, in order', function (): void {
    $demoRequest = DemoRequest::factory()->create(['tools' => [Tool::LeySilla, Tool::Nom035]])->fresh();

    expect($demoRequest->tools->all())->toBe([Tool::LeySilla, Tool::Nom035]);
});

it('accepts a union and every tool inserted directly', function (): void {
    DB::table('demo_requests')->insert([
        ...DemoRequest::factory()->raw(),
        'id' => (string) Str::uuid(),
        'profile' => 'union',
        'tools' => '["nom_035","nom_037","ley_silla","complaints","compensation"]',
    ]);

    expect(DemoRequest::query()->count())->toBe(1);
});

/** The eight columns of where a request came from, and the longest text each one holds. */
const ATTRIBUTION_COLUMNS = [
    'utm_source' => 100,
    'utm_medium' => 100,
    'utm_campaign' => 100,
    'referrer_host' => 255,
    'first_utm_source' => 100,
    'first_utm_medium' => 100,
    'first_utm_campaign' => 100,
    'first_referrer_host' => 255,
];

/** @return array<string, array{string, int}> */
function attributionColumns(): array
{
    return array_map(fn (int $max, string $column): array => [$column, $max], ATTRIBUTION_COLUMNS, array_keys(ATTRIBUTION_COLUMNS));
}

it('keeps where a request came from as nullable columns: a request without it has none', function (): void {
    $demoRequest = DemoRequest::factory()->create()->fresh();

    foreach (array_keys(ATTRIBUTION_COLUMNS) as $column) {
        expect($demoRequest->{$column})->toBeNull();
    }
});

it('accepts the longest text of each attribution column and refuses one more', function (string $column, int $max): void {
    $row = [...DemoRequest::factory()->raw(), 'tools' => '["nom_035"]'];

    DB::table('demo_requests')->insert([...$row, 'id' => (string) Str::uuid(), $column => str_repeat('a', $max)]);

    expect(DemoRequest::query()->count())->toBe(1)
        ->and(fn () => DB::table('demo_requests')->insert([...$row, 'id' => (string) Str::uuid(), $column => str_repeat('a', $max + 1)]))
        ->toThrow(QueryException::class);
})->with(fn (): array => attributionColumns());

it('refuses an empty or blank attribution column, so "no data" is only null', function (string $column, int $max, string $value): void {
    $row = [...DemoRequest::factory()->raw(), 'tools' => '["nom_035"]', 'id' => (string) Str::uuid(), $column => $value];

    try {
        DB::table('demo_requests')->insert($row);
        $this->fail("{$column} should refuse a blank value.");
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('23514');
    }
})->with(function (): array {
    $cases = [];

    foreach (ATTRIBUTION_COLUMNS as $column => $max) {
        $cases["{$column} empty"] = [$column, $max, ''];
        $cases["{$column} spaces"] = [$column, $max, '   '];
    }

    return $cases;
});

it('builds a request that came from a campaign, with the first contact equal to it by default', function (): void {
    $demoRequest = DemoRequest::factory()->fromCampaign()->create()->fresh();

    expect($demoRequest->utm_source)->toBe('google')
        ->and($demoRequest->utm_medium)->toBe('cpc')
        ->and($demoRequest->utm_campaign)->toBe('nom-035')
        ->and($demoRequest->referrer_host)->toBeNull()
        ->and($demoRequest->first_utm_source)->toBe('google')
        ->and($demoRequest->first_utm_campaign)->toBe('nom-035');
});

it('builds a request that was referred by a site, by its host only', function (): void {
    $demoRequest = DemoRequest::factory()->referredBy('www.google.com')->create()->fresh();

    expect($demoRequest->referrer_host)->toBe('www.google.com')
        ->and($demoRequest->first_referrer_host)->toBe('www.google.com')
        ->and($demoRequest->utm_source)->toBeNull();
});
