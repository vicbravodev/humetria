<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ReconcileWhatsApp;
use App\Modules\Messaging\Contracts\DeliveryOutcomes;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Support\DeliveryLedger;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

mutates(ReconcileWhatsApp::class, DeliveryLedger::class);

/** A message charged in the month, with the status it ended in (null: its delivery row is gone). */
function chargedMessage(Tenant $tenant, ?DeliveryStatus $status, string $month = '2026-11-01', int $price = 221_000, int $cost = 170_000): string
{
    $id = (string) Str::uuid();
    $status !== null && DeliveryFactory::new()->create(['id' => $id, 'tenant_id' => $tenant->id, 'status' => $status]);
    DB::table('usage_events')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'meter' => 'whatsapp', 'period_start' => $month, 'quantity' => 1, 'idempotency_key' => $id, 'occurred_at' => "{$month} 12:00:00", 'unit_cost_micros' => $cost, 'unit_price_micros' => $price, 'cost_currency' => 'MXN']);

    return $id;
}

it('says which of the deliveries did not end delivered or read', function (): void {
    $tenant = Tenant::factory()->create();
    $ids = [
        DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Delivered])->id,
        DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Read])->id,
        $sent = DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Sent])->id,
        $failed = DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Failed])->id,
        $unknown = DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Unknown])->id,
    ];

    $undelivered = resolve(DeliveryOutcomes::class)->undelivered([...$ids, (string) Str::uuid()]);

    expect($undelivered)->toEqualCanonicalizing([$sent, $failed, $unknown])
        ->and(resolve(DeliveryOutcomes::class)->undelivered([]))->toBe([]);
});

it('tells each company what it was charged, what was not delivered and the credit that suggests, and the cost of the platform', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    chargedMessage($a, DeliveryStatus::Delivered);
    chargedMessage($a, DeliveryStatus::Read);
    chargedMessage($a, DeliveryStatus::Failed);
    chargedMessage($a, DeliveryStatus::Sent, price: 300_000);
    chargedMessage($b, DeliveryStatus::Delivered);
    chargedMessage($b, null);
    chargedMessage($a, DeliveryStatus::Failed, month: '2026-10-01');

    $report = resolve(ReconcileWhatsApp::class)->handle(CarbonImmutable::parse('2026-11-18'));

    $byCompany = collect($report['companies'])->keyBy('tenantId');
    expect($byCompany[$a->id])->toMatchArray(['charged' => 4, 'undelivered' => 2, 'creditCents' => 52])
        ->and($byCompany[$b->id])->toMatchArray(['charged' => 2, 'undelivered' => 0, 'creditCents' => 0])
        ->and($report['platformCostCents'])->toBe(102);
});

it('runs from the command with the month and prints one line per company and the total', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    chargedMessage($tenant, DeliveryStatus::Failed);
    chargedMessage($tenant, DeliveryStatus::Delivered);

    $this->artisan('billing:whatsapp-reconcile', ['month' => '2026-11'])
        ->expectsOutputToContain('Autopartes del Bajío: 2 cobrados, 1 sin entregar, crédito sugerido 22 centavos')
        ->expectsOutputToContain('Costo de Meta del mes: 34 centavos')
        ->assertSuccessful();
});

it('refuses a month that is not one', function (): void {
    $this->artisan('billing:whatsapp-reconcile', ['month' => 'noviembre'])->assertFailed();
});

it('is a manual command: it has no schedule', function (): void {
    $scheduled = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'billing:whatsapp-reconcile'));

    expect($scheduled)->toBeFalse();
});
