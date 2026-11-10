<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantRegistry;

mutates(TenantRegistry::class);

it('describes a company for another module: names, RFC, status and how many centers it uses', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Acme S.A. de C.V.', 'rfc' => 'ACM010101AB1', 'slug' => 'acme']);
    WorkCenter::factory()->for($tenant)->count(2)->create();
    WorkCenter::factory()->for($tenant)->create(['archived_at' => now()]);

    $brief = resolve(CompanyRegistry::class)->find($tenant->id);

    expect($brief)->not->toBeNull()
        ->and($brief?->id)->toBe($tenant->id)
        ->and($brief?->tradeName)->toBe('Acme')
        ->and($brief?->legalName)->toBe('Acme S.A. de C.V.')
        ->and($brief?->rfc)->toBe('ACM010101AB1')
        ->and($brief?->slug)->toBe('acme')
        ->and($brief?->status)->toBe('not_started')
        ->and($brief?->statusLabel)->toBe('Sin iniciar')
        ->and($brief?->workCenters)->toBe(2)
        ->and($brief?->withdrawn)->toBeFalse();
});

it('says a suspended or archived company is withdrawn, and a read-only one is not', function (): void {
    $suspended = Tenant::factory()->suspended()->create();
    $archived = Tenant::factory()->archived()->create();
    $readOnly = Tenant::factory()->readOnly()->create();
    $registry = resolve(CompanyRegistry::class);

    expect($registry->find($suspended->id)?->withdrawn)->toBeTrue()
        ->and($registry->find($archived->id)?->withdrawn)->toBeTrue()
        ->and($registry->find($readOnly->id)?->withdrawn)->toBeFalse()
        ->and($registry->find($readOnly->id)?->status)->toBe('read_only');
});

it('finds nothing for an id that is not a company, whatever it looks like', function (): void {
    expect(resolve(CompanyRegistry::class)->find('no-es-uuid'))->toBeNull()
        ->and(resolve(CompanyRegistry::class)->find('11111111-1111-4111-8111-111111111111'))->toBeNull();
});

it('describes many companies at once, by id, counting centers in one place for all', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    WorkCenter::factory()->for($a)->count(3)->create();
    WorkCenter::factory()->for($b)->create();
    Tenant::factory()->create();

    $found = resolve(CompanyRegistry::class)->many([$a->id, $b->id, 'no-es-uuid', '11111111-1111-4111-8111-111111111111']);

    expect(array_keys($found))->toEqualCanonicalizing([$a->id, $b->id])
        ->and($found[$a->id]->workCenters)->toBe(3)
        ->and($found[$b->id]->workCenters)->toBe(1)
        ->and(resolve(CompanyRegistry::class)->many([]))->toBe([]);
});

it('searches companies by trade name, legal name or RFC, without regard to case or accents, by name, up to a limit', function (): void {
    Tenant::factory()->create(['trade_name' => 'Zapatería José', 'legal_name' => 'Calzado del Norte S.A.', 'rfc' => 'ZAP010101AB1']);
    Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Jose Hermanos S.A.', 'rfc' => 'ACM010101AB1']);
    Tenant::factory()->create(['trade_name' => 'Otra', 'legal_name' => 'Otra S.A.', 'rfc' => 'OTR010101AB1']);
    $registry = resolve(CompanyRegistry::class);

    expect(array_map(fn ($brief): string => $brief->tradeName, $registry->search('JOSE')))->toBe(['Acme', 'Zapatería José'])
        ->and(array_map(fn ($brief): string => $brief->tradeName, $registry->search('otr010101')))->toBe(['Otra'])
        ->and($registry->search('nada que ver'))->toBe([])
        ->and(count($registry->search('', 2)))->toBe(2)
        ->and(array_map(fn ($brief): string => $brief->tradeName, $registry->search('', 1)))->toBe(['Acme']);
});
