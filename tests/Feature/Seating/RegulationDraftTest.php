<?php

declare(strict_types=1);

use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\RegulationDraft;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(RegulationDraft::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

it('drafts the three parts and the usage rule from the data of the center', function (): void {
    $cashier = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera', 'continuous_minutes' => 240]);
    $stock = StandingPosition::factory()->nearby()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Almacenista', 'continuous_minutes' => 90, 'figure_one_applies' => false]);
    PauseAgreement::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $cashier->id, 'pauses_per_shift' => 3, 'minutes_each' => 10, 'agreed_on' => '2026-01-01']);
    PauseAgreement::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $cashier->id, 'pauses_per_shift' => 4, 'minutes_each' => 15, 'agreed_on' => '2026-02-01']);
    Seat::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $cashier->id, 'quantity' => 2]);
    Seat::factory()->restArea()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'location' => 'Comedor, planta baja']);

    $parts = resolve(RegulationDraft::class)->for($this->center->id);

    expect($parts->riskFactors)->toContain('Cajera')->toContain('Almacenista')->toContain('240 minutos')->toContain('tiempo de pie')
        ->and($parts->pauses)->toContain('Cajera: 4 pausas por turno de 15 minutos')->not->toContain('3 pausas')->toContain('Almacenista: por acordar')
        ->and($parts->seatLocation)->toContain('Comedor, planta baja')->toContain('Cajera: 2')
        ->and($parts->usageRule)->toContain('asiento')->toContain('a disposición de todas las personas trabajadoras');
});

it('drafts every part even when the center has no data yet, saying what is missing', function (): void {
    $parts = resolve(RegulationDraft::class)->for($this->center->id);

    foreach ([$parts->riskFactors, $parts->pauses, $parts->seatLocation, $parts->usageRule] as $text) {
        expect(trim($text))->not->toBe('');
    }
    expect($parts->riskFactors)->toContain('Registra los puestos')->and($parts->pauses)->toContain('Registra los acuerdos');
});

it('does not read the data of another center or another company', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'name' => 'Ajena']);
    StandingPosition::factory()->create(['name' => 'De otra empresa']);

    expect(resolve(RegulationDraft::class)->for($this->center->id)->riskFactors)->not->toContain('Ajena')->not->toContain('De otra empresa');
});
