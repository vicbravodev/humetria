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
