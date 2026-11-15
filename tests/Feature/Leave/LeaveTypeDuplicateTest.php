<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\SaveLeaveType;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;

mutates(SaveLeaveType::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    resolve(LeaveDefaults::class)->ensure();
});

/**
 * @return array<string, list<string>>
 */
function duplicateErrors(string $name): array
{
    try {
        resolve(SaveLeaveType::class)->handle($name, true);
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

it('refuses a type of the company that is a type of the law with other words', function (string $name): void {
    expect(duplicateErrors($name)['name'][0] ?? null)->toContain('ya viene de la ley');
})->with([
    'with «permiso de»' => ['Permiso de paternidad'],
    'with another case and no accent' => ['PERMISO DE ADOPCION'],
    'with «por»' => ['Permiso por maternidad'],
    'the very name, apart' => ['  paternidad  '],
    'special with pay' => ['Permiso especial con goce de sueldo'],
]);

it('names the type of the law it repeats, so RH knows which to use', function (): void {
    expect(duplicateErrors('Permiso de paternidad')['name'][0])->toBe('«Paternidad» ya viene de la ley: úsalo en vez de crear otro igual.');
});

it('still lets the company create a type that is its own', function (string $name): void {
    expect(duplicateErrors($name))->toBe([])
        ->and(LeaveType::query()->where('name', $name)->exists())->toBeTrue();
})->with(['Permiso de mudanza', 'Día de cumpleaños', 'Permiso especial por duelo']);

it('does not touch the types of the law themselves', function (): void {
    expect(LeaveType::query()->whereNotNull('key')->count())->toBe(5);
});
