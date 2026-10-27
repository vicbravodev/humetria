<?php

declare(strict_types=1);

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
    DB::table('demo_requests')->insert([...DemoRequest::factory()->raw(), 'id' => (string) Str::uuid(), 'message' => str_repeat('a', 2000)]);

    expect(DemoRequest::query()->count())->toBe(1);
});

it('rejects a row the form would reject', function (string $column, mixed $value): void {
    $row = [...DemoRequest::factory()->raw(), 'id' => (string) Str::uuid(), $column => $value];

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
]);
