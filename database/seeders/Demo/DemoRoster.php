<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoMail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * The 180 people of Manufacturas Alba del Bajío, written by hand and the same on every run (no Faker: production does not
 * have it). The heads have their own names; the rest take theirs from fixed lists by their place in the roster. Each
 * person's id is derived from their key, so their portal link survives a reset.
 */
final class DemoRoster
{
    /** By key, the work centers and their names: 15, 30, 51 and 84 people, the NOM-035 thresholds (I; I and II; I and III). */
    public const array CENTERS = [
        'monterrey' => 'Sucursal Monterrey',
        'offices' => 'Oficinas Querétaro',
        'distribution' => 'Centro de distribución',
        'plant' => 'Planta Querétaro',
    ];

    /** The director: owner of the company in the panel. */
    public const string OWNER = 'director';

    /** The head of human resources: the HR member of the panel. */
    public const string HR = 'hr-head';

    /** How many rank-and-file people of each center keep their invitations pending for the door: 40 in all. */
    private const array DOOR = ['monterrey' => 3, 'offices' => 7, 'distribution' => 11, 'plant' => 19];

    /** Fixed: changing it changes every id, and every portal link with them. */
    private const string ID_NAMESPACE = '3f0d8a52-5c1e-4b7a-9a43-6f2e1d0c9b88';

    /**
     * The organization, managers before the people under them: center, area, position, job category and level, how many,
     * the key of a head (who gets a name of their own) and whom they report to.
     *
     * @var list<array{string, string, string, string, string, int, string|null, string|null}>
     */
    private const array POSITIONS = [
        ['plant', 'Dirección', 'Director general', 'Dirección', 'D1', 1, 'director', null],
        ['plant', 'Dirección', 'Gerente de planta', 'Gerencia', 'G1', 1, 'plant-manager', 'director'],
        ['plant', 'Producción turno 1', 'Supervisor de producción', 'Jefatura', 'J1', 1, 'shift-1-supervisor', 'plant-manager'],
        ['plant', 'Producción turno 1', 'Operador de producción', 'Operativo', 'O1', 20, null, 'shift-1-supervisor'],
        ['plant', 'Producción turno 1', 'Operador de prensa', 'Operativo', 'O2', 5, null, 'shift-1-supervisor'],
        ['plant', 'Producción turno 1', 'Técnico de proceso', 'Operativo', 'O3', 2, null, 'shift-1-supervisor'],
        ['plant', 'Producción turno 2', 'Supervisor de producción', 'Jefatura', 'J1', 1, 'shift-2-supervisor', 'plant-manager'],
        ['plant', 'Producción turno 2', 'Operador de producción', 'Operativo', 'O1', 19, null, 'shift-2-supervisor'],
        ['plant', 'Producción turno 2', 'Operador de prensa', 'Operativo', 'O2', 4, null, 'shift-2-supervisor'],
        ['plant', 'Producción turno 2', 'Técnico de proceso', 'Operativo', 'O3', 2, null, 'shift-2-supervisor'],
        ['plant', 'Calidad', 'Jefa de calidad', 'Jefatura', 'J1', 1, 'quality-head', 'plant-manager'],
        ['plant', 'Calidad', 'Inspector de calidad', 'Operativo', 'O2', 7, null, 'quality-head'],
        ['plant', 'Calidad', 'Analista de metrología', 'Administrativo', 'A2', 2, null, 'quality-head'],
        ['plant', 'Mantenimiento', 'Jefe de mantenimiento', 'Jefatura', 'J1', 1, 'maintenance-head', 'plant-manager'],
        ['plant', 'Mantenimiento', 'Técnico de mantenimiento', 'Operativo', 'O3', 7, null, 'maintenance-head'],
        ['plant', 'Mantenimiento', 'Electricista industrial', 'Operativo', 'O3', 2, null, 'maintenance-head'],
        ['plant', 'Almacén', 'Jefe de almacén', 'Jefatura', 'J1', 1, 'plant-warehouse-head', 'plant-manager'],
        ['plant', 'Almacén', 'Almacenista', 'Operativo', 'O1', 5, null, 'plant-warehouse-head'],
        ['plant', 'Almacén', 'Montacarguista', 'Operativo', 'O2', 2, null, 'plant-warehouse-head'],
        ['distribution', 'Logística', 'Gerente de centro de distribución', 'Gerencia', 'G1', 1, 'distribution-manager', 'director'],
        ['distribution', 'Logística', 'Planeador de demanda', 'Administrativo', 'A2', 2, null, 'distribution-manager'],
        ['distribution', 'Logística', 'Coordinador de tráfico', 'Administrativo', 'A2', 2, null, 'distribution-manager'],
        ['distribution', 'Almacén', 'Jefe de almacén', 'Jefatura', 'J1', 1, 'distribution-warehouse-head', 'distribution-manager'],
        ['distribution', 'Almacén', 'Auxiliar de almacén', 'Operativo', 'O1', 20, null, 'distribution-warehouse-head'],
        ['distribution', 'Almacén', 'Montacarguista', 'Operativo', 'O2', 6, null, 'distribution-warehouse-head'],
        ['distribution', 'Almacén', 'Surtidor de pedidos', 'Operativo', 'O1', 3, null, 'distribution-warehouse-head'],
        ['distribution', 'Transporte', 'Jefe de transporte', 'Jefatura', 'J1', 1, 'transport-head', 'distribution-manager'],
        ['distribution', 'Transporte', 'Chofer de reparto', 'Operativo', 'O2', 9, null, 'transport-head'],
        ['distribution', 'Transporte', 'Auxiliar de reparto', 'Operativo', 'O1', 2, null, 'transport-head'],
        ['distribution', 'Administración', 'Jefa de administración', 'Jefatura', 'J1', 1, 'distribution-admin-head', 'distribution-manager'],
        ['distribution', 'Administración', 'Auxiliar administrativo', 'Administrativo', 'A1', 3, null, 'distribution-admin-head'],
        ['offices', 'Finanzas', 'Gerente de administración y finanzas', 'Gerencia', 'G1', 1, 'offices-manager', 'director'],
        ['offices', 'Finanzas', 'Contador', 'Administrativo', 'A2', 4, null, 'offices-manager'],
        ['offices', 'Finanzas', 'Auxiliar contable', 'Administrativo', 'A1', 3, null, 'offices-manager'],
        ['offices', 'Recursos humanos', 'Jefa de recursos humanos', 'Jefatura', 'J1', 1, 'hr-head', 'offices-manager'],
        ['offices', 'Recursos humanos', 'Analista de recursos humanos', 'Administrativo', 'A2', 3, null, 'hr-head'],
        ['offices', 'Recursos humanos', 'Auxiliar de nómina', 'Administrativo', 'A1', 2, null, 'hr-head'],
        ['offices', 'Ventas', 'Jefe de ventas', 'Jefatura', 'J1', 1, 'sales-head', 'offices-manager'],
        ['offices', 'Ventas', 'Ejecutivo de ventas', 'Administrativo', 'A2', 7, null, 'sales-head'],
        ['offices', 'Ventas', 'Asistente comercial', 'Administrativo', 'A1', 2, null, 'sales-head'],
        ['offices', 'Sistemas', 'Jefe de sistemas', 'Jefatura', 'J1', 1, 'it-head', 'offices-manager'],
        ['offices', 'Sistemas', 'Desarrollador', 'Administrativo', 'A2', 3, null, 'it-head'],
        ['offices', 'Sistemas', 'Soporte técnico', 'Administrativo', 'A1', 2, null, 'it-head'],
        ['monterrey', 'Ventas', 'Gerente de sucursal', 'Gerencia', 'G1', 1, 'branch-manager', 'director'],
        ['monterrey', 'Ventas', 'Ejecutivo de ventas', 'Administrativo', 'A2', 6, null, 'branch-manager'],
        ['monterrey', 'Ventas', 'Asistente comercial', 'Administrativo', 'A1', 2, null, 'branch-manager'],
        ['monterrey', 'Almacén', 'Encargado de almacén', 'Jefatura', 'J1', 1, 'branch-warehouse-head', 'branch-manager'],
        ['monterrey', 'Almacén', 'Auxiliar de almacén', 'Operativo', 'O1', 4, null, 'branch-warehouse-head'],
        ['monterrey', 'Almacén', 'Chofer de reparto', 'Operativo', 'O2', 1, null, 'branch-warehouse-head'],
    ];

    /**
     * The heads, by key: name, email user and gender.
     *
     * @var array<string, array{string, string, 'female'|'male'}>
     */
    private const array HEADS = [
        'director' => ['Ricardo Alba Mendoza', 'ricardo.alba', 'male'],
        'plant-manager' => ['Jorge Villarreal Ibarra', 'jorge.villarreal', 'male'],
        'shift-1-supervisor' => ['Arturo Núñez Salinas', 'arturo.nunez', 'male'],
        'shift-2-supervisor' => ['Leticia Cabrera Olvera', 'leticia.cabrera', 'female'],
        'quality-head' => ['Paola Esquivel Rangel', 'paola.esquivel', 'female'],
        'maintenance-head' => ['Raúl Zamora Tovar', 'raul.zamora', 'male'],
        'plant-warehouse-head' => ['Efraín Bautista Luna', 'efrain.bautista', 'male'],
        'distribution-manager' => ['Sandra Galindo Robles', 'sandra.galindo', 'female'],
        'distribution-warehouse-head' => ['Tomás Arellano Quiroz', 'tomas.arellano', 'male'],
        'transport-head' => ['Felipe Montes Carrillo', 'felipe.montes', 'male'],
        'distribution-admin-head' => ['Irma Saldaña Paredes', 'irma.saldana', 'female'],
        'offices-manager' => ['Gonzalo Peña Arriaga', 'gonzalo.pena', 'male'],
        'hr-head' => ['Mariana Ochoa Villaseñor', 'mariana.ochoa', 'female'],
        'sales-head' => ['Héctor Lozano Cervantes', 'hector.lozano', 'male'],
        'it-head' => ['Daniel Ponce Valdivia', 'daniel.ponce', 'male'],
        'branch-manager' => ['Cecilia Garza Treviño', 'cecilia.garza', 'female'],
        'branch-warehouse-head' => ['Ramiro Elizondo Cantú', 'ramiro.elizondo', 'male'],
    ];

    private const array WOMEN = [
        'María José', 'Guadalupe', 'Fernanda', 'Daniela', 'Alejandra', 'Gabriela', 'Verónica', 'Adriana', 'Claudia', 'Rosa María',
        'Patricia', 'Mónica', 'Karla', 'Diana', 'Andrea', 'Sofía', 'Valeria', 'Ximena', 'Lucía', 'Brenda',
        'Araceli', 'Yolanda', 'Norma', 'Silvia', 'Elizabeth', 'Jessica', 'Itzel', 'Montserrat', 'Rocío', 'Lorena',
        'Elena', 'Miriam', 'Teresa', 'Beatriz', 'Carmen', 'Isabel', 'Natalia', 'Abigail', 'Esmeralda', 'Liliana',
    ];

    private const array MEN = [
        'José Luis', 'Juan Carlos', 'Miguel Ángel', 'Luis Fernando', 'Francisco', 'Alejandro', 'Eduardo', 'Roberto', 'Fernando', 'Javier',
        'Sergio', 'Óscar', 'Enrique', 'Manuel', 'Rafael', 'Martín', 'Pedro', 'Gerardo', 'Alfredo', 'Ernesto',
        'Hugo', 'Iván', 'Omar', 'Jesús', 'Ángel', 'Diego', 'Emilio', 'Rubén', 'Salvador', 'Adrián',
        'Armando', 'Gustavo', 'Rodrigo', 'Mauricio', 'Ulises', 'Samuel', 'Andrés', 'Benjamín', 'Joaquín', 'Marco Antonio',
    ];

    private const array SURNAMES = [
        'Hernández', 'García', 'Martínez', 'López', 'González', 'Rodríguez', 'Pérez', 'Sánchez', 'Ramírez', 'Cruz',
        'Flores', 'Gómez', 'Morales', 'Vázquez', 'Reyes', 'Jiménez', 'Torres', 'Díaz', 'Gutiérrez', 'Ruiz',
        'Aguilar', 'Ortiz', 'Moreno', 'Castillo', 'Romero', 'Álvarez', 'Méndez', 'Chávez', 'Rivera', 'Juárez',
        'Ramos', 'Domínguez', 'Herrera', 'Medina', 'Castro', 'Vargas', 'Rosales', 'Navarro',
    ];

    /** @var list<DemoPerson>|null */
    private static ?array $people = null;

    /**
     * Everyone, managers before the people under them.
     *
     * @return list<DemoPerson>
     */
    public static function people(): array
    {
        return self::$people ??= self::build();
    }

    public static function person(string $key): DemoPerson
    {
        foreach (self::people() as $person) {
            if ($person->key === $key) {
                return $person;
            }
        }

        throw new InvalidArgumentException("No one in the demo roster has the key [{$key}].");
    }

    /** The person's employee id: a UUID v5 of their key, the same on every run. */
    public static function employeeId(string $key): string
    {
        return Uuid::uuid5(self::ID_NAMESPACE, $key)->toString();
    }

    /** @return list<DemoPerson> */
    private static function build(): array
    {
        $people = [];
        $staff = 0;

        foreach (self::POSITIONS as [$center, $area, $position, $category, $level, $count, $head, $reportsTo]) {
            for ($i = 0; $i < $count; $i++) {
                $number = count($people) + 1;
                [$name, $user, $gender] = $head === null ? self::staffName($staff) : self::HEADS[$head];
                $people[] = new DemoPerson(
                    key: $head ?? sprintf('%s-%03d', $center, $staff + 1),
                    name: $name,
                    email: $user.'@'.DemoMail::DOMAIN,
                    gender: $gender,
                    centerKey: $center,
                    area: $area,
                    position: $position,
                    category: $category,
                    level: $level,
                    // Heads have been longer with the company: between 4 and 15 years; the rest, from 2 months to 10 years.
                    tenureDays: $head === null ? 60 + ($staff * 211) % 3600 : 1460 + ($number * 397) % 4000,
                    reportsTo: $reportsTo,
                    reservedForDoor: false,
                    employeeNumber: sprintf('MAB-%04d', $number),
                );
                $staff += $head === null ? 1 : 0;
            }
        }

        return self::reserveForDoor($people);
    }

    /**
     * The name, email user and gender of the n-th rank-and-file person: 4 in 9 are women.
     *
     * @return array{string, string, 'female'|'male'}
     */
    private static function staffName(int $n): array
    {
        $woman = $n % 9 < 4;
        $first = $woman ? self::WOMEN[(intdiv($n, 9) * 4 + $n % 9) % 40] : self::MEN[(intdiv($n, 9) * 5 + $n % 9 - 4) % 40];
        $paternal = self::SURNAMES[($n * 5) % 38];
        $maternal = self::SURNAMES[($n * 11 + 7) % 38];
        $user = Str::of(Str::ascii(Str::before($first, ' ').'.'.$paternal.'.'.Str::substr($maternal, 0, 1)))->lower()->toString();

        return ["{$first} {$paternal} {$maternal}", $user, $woman ? 'female' : 'male'];
    }

    /**
     * Marks the door's people in each center, spread evenly over its rank and file so no area is left without answers.
     *
     * @param  list<DemoPerson>  $people
     * @return list<DemoPerson>
     */
    private static function reserveForDoor(array $people): array
    {
        $heads = array_keys(self::HEADS);
        $taken = [];

        foreach (self::DOOR as $center => $quota) {
            $staff = array_values(array_filter($people, fn (DemoPerson $person): bool => $person->centerKey === $center && ! in_array($person->key, $heads, true)));
            $total = count($staff);

            foreach ($staff as $i => $person) {
                if (intdiv(($i + 1) * $quota, $total) > intdiv($i * $quota, $total)) {
                    $taken[$person->key] = true;
                }
            }
        }

        return array_map(fn (DemoPerson $person): DemoPerson => isset($taken[$person->key]) ? new DemoPerson(
            $person->key, $person->name, $person->email, $person->gender, $person->centerKey, $person->area, $person->position,
            $person->category, $person->level, $person->tenureDays, $person->reportsTo, true, $person->employeeNumber,
        ) : $person, $people);
    }
}
