<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\PersonNamesRedactor;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(PersonNamesRedactor::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $center = WorkCenter::factory()->for($this->tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach (['Juan Pérez Gómez', 'María Fernanda Ruiz', 'Cher'] as $name) {
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'area_id' => $area->id, 'name' => $name]);
    }

    $other = Tenant::factory()->create();
    Employee::factory()->create(['tenant_id' => $other->id, 'work_center_id' => WorkCenter::factory()->for($other)->create()->id, 'area_id' => Area::factory()->create(['tenant_id' => $other->id])->id, 'name' => 'Luis Ajeno Díaz']);

    workIn($this->tenant);
});

it('masks the name of a person of the company however it is written', function (string $text, string $expected): void {
    expect(resolve(PersonNamesRedactor::class)->redact($text))->toBe($expected);
})->with([
    'the full name' => ['¿Cómo va Juan Pérez Gómez?', '¿Cómo va [persona]?'],
    'name and first surname' => ['¿Cómo va Juan Pérez?', '¿Cómo va [persona]?'],
    'without accents and in lowercase' => ['dime de juan perez', 'dime de [persona]'],
    'with a double space' => ['dime de Juan  Pérez', 'dime de [persona]'],
    'a compound given name' => ['y María Fernanda Ruiz?', 'y [persona]?'],
    'several people' => ['Juan Pérez y María Fernanda Ruiz', '[persona] y [persona]'],
]);

it('leaves alone what is not the name of a person of the company', function (string $text): void {
    expect(resolve(PersonNamesRedactor::class)->redact($text))->toBe($text);
})->with([
    'a first name alone' => ['Juan vino hoy'],
    'a surname alone' => ['la familia Pérez'],
    'a one-word name' => ['Cher cantó'],
    'a compliance question' => ['¿Qué falta de la NOM-035 en la planta?'],
    'a name of another company' => ['Luis Ajeno Díaz'],
    'a word that only starts like a name' => ['Juana Pérez'],
]);

it('sends the text back as it came when the company has nobody', function (): void {
    workIn(Tenant::factory()->create());

    expect(resolve(PersonNamesRedactor::class)->redact('Juan Pérez'))->toBe('Juan Pérez');
});

it('masks the name of a person in a company of thousands, where one pattern of every name would be too large to compile', function (): void {
    $names = array_map(fn (int $i): string => sprintf('Nombre%d Apellido%d Segundo%d', $i, $i, $i), range(1, 5000));
    $roster = Mockery::mock(Roster::class);
    $roster->shouldReceive('names')->andReturn($names);

    expect((new PersonNamesRedactor($roster))->redact('¿Cómo va Nombre4321 Apellido4321?'))->toBe('¿Cómo va [persona]?');
});

it('reads every accented vowel, grave, acute or with diaeresis, as the plain one', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'area_id' => Area::factory()->create(['tenant_id' => $this->tenant->id])->id, 'name' => 'Áàäéèë Íìïóòöúùü']);

    expect(resolve(PersonNamesRedactor::class)->redact('dime de aaaeee iiiooouuu'))->toBe('dime de [persona]');
});
