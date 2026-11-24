<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Support\PermissionAreas;
use App\Modules\Tenancy\Support\PermissionCatalog;
use Spatie\Permission\Models\Role;

/**
 * The areas of the roles screen (one access level per area, plus a few extras) are written once in
 * tests/Fixtures/permissions/areas.json; resources/js/lib/permission-areas.ts draws them and tests/js/lib/permission-areas.test.ts
 * holds it to the same file. Here the file is held to the catalog and to the templates, so no permission is left without a
 * control and no template starts a role in a state the screen cannot show.
 *
 * @return list<array{key: string, section: string, levels: array<string, list<string>>, extras: list<string>}>
 */
function permissionAreas(): array
{
    $decoded = json_decode((string) file_get_contents(base_path('tests/Fixtures/permissions/areas.json')), true, flags: JSON_THROW_ON_ERROR);
    $areas = [];

    foreach (is_array($decoded) ? $decoded : [] as $area) {
        if (! is_array($area) || ! is_string($area['key'] ?? null) || ! is_string($area['section'] ?? null) || ! is_array($area['levels'] ?? null)) {
            throw new UnexpectedValueException('An area of areas.json is not {key, section, levels, extras}.');
        }

        $levels = [];

        foreach ($area['levels'] as $level => $permissions) {
            $levels[is_string($level) ? $level : throw new UnexpectedValueException('A level of areas.json has no name.')] = namesIn($permissions);
        }

        $areas[] = ['key' => $area['key'], 'section' => $area['section'], 'levels' => $levels, 'extras' => namesIn($area['extras'] ?? null)];
    }

    return $areas;
}

/** @return list<string> */
function namesIn(mixed $permissions): array
{
    if (! is_array($permissions) || array_filter($permissions, fn (mixed $name): bool => ! is_string($name)) !== []) {
        throw new UnexpectedValueException('A list of permissions of areas.json holds something else than names.');
    }

    return array_values(array_filter($permissions, is_string(...)));
}

/**
 * Where each permission is first given: the lowest level that grants it, or an extra.
 *
 * @return list<string>
 */
function firstGrants(): array
{
    $names = [];

    foreach (permissionAreas() as $area) {
        $seen = [];

        foreach ($area['levels'] as $permissions) {
            $names = [...$names, ...array_values(array_diff($permissions, $seen))];
            $seen = [...$seen, ...$permissions];
        }

        $names = [...$names, ...$area['extras']];
    }

    return $names;
}

it('gives every permission the owner can hand out exactly one place, and none of the owner\'s own', function (): void {
    $handedOut = array_values(array_map(
        fn ($permission): string => $permission->name,
        array_filter(PermissionCatalog::all(), fn ($permission): bool => ! $permission->ownerOnly),
    ));

    expect(firstGrants())->toEqualCanonicalizing($handedOut)
        ->and(firstGrants())->toHaveCount(count(array_unique(firstGrants())))
        ->and(firstGrants())->not->toContain('manage-members');
});

it('starts every area with no access and makes each level include the one before', function (): void {
    foreach (permissionAreas() as $area) {
        $levels = array_values($area['levels']);

        expect(array_key_first($area['levels']))->toBe('none')
            ->and($levels[0])->toBe([])
            ->and(count($levels))->toBeGreaterThanOrEqual(2);

        for ($level = 1; $level < count($levels); $level++) {
            expect(array_diff($levels[$level - 1], $levels[$level]))->toBe([])
                ->and(count($levels[$level]))->toBeGreaterThan(count($levels[$level - 1]));
        }
    }
});

it('puts every area in one of the four groups of the sidebar', function (): void {
    expect(array_values(array_unique(array_column(permissionAreas(), 'section'))))->toEqualCanonicalizing(['general', 'compliance', 'listening', 'operations'])
        ->and(array_column(permissionAreas(), 'key'))->toHaveCount(count(array_unique(array_column(permissionAreas(), 'key'))));
});

it('reads each template as one clean level per area plus its extras, with nothing left over', function (TenantRole $template): void {
    $held = namesIn(Role::query()->whereNull('tenant_id')->where('name', $template->value)->sole()->permissions->pluck('name')->all());
    $placed = [];

    foreach (permissionAreas() as $area) {
        $inArea = array_values(array_intersect($held, array_merge(...array_values($area['levels']))));
        $matches = array_filter($area['levels'], fn (array $permissions): bool => array_diff($permissions, $inArea) === [] && array_diff($inArea, $permissions) === []);

        expect($matches)->toHaveCount(1, "{$template->value} in {$area['key']}");
        $placed = [...$placed, ...$inArea, ...array_intersect($held, $area['extras'])];
    }

    expect(array_diff($held, $placed))->toBe([]);
})->with([TenantRole::Hr, TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('has the same areas, levels and extras on the server, where Humi reads them, as in areas.json', function (): void {
    $server = array_map(fn (string $key, array $area): array => [
        'key' => $key,
        'levels' => array_map(fn (array $level): array => $level['permissions'], $area['levels']),
        'extras' => array_keys($area['extras']),
    ], array_keys(PermissionAreas::all()), PermissionAreas::all());

    expect($server)->toBe(array_map(fn (array $area): array => ['key' => $area['key'], 'levels' => $area['levels'], 'extras' => $area['extras']], permissionAreas()));
});

it('calls each area and each level on the server, where Humi says them, as areas.json does', function (): void {
    $decoded = json_decode((string) file_get_contents(base_path('tests/Fixtures/permissions/areas.json')), true, flags: JSON_THROW_ON_ERROR);
    $fixture = array_map(fn (array $area): array => ['label' => $area['label'] ?? null, 'levels' => $area['levelLabels'] ?? null], is_array($decoded) ? $decoded : []);

    expect(array_values(array_map(fn (array $area): array => [
        'label' => $area['label'],
        'levels' => array_map(fn (array $level): string => $level['label'], $area['levels']),
    ], PermissionAreas::all())))->toBe($fixture);
});
