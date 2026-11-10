<?php

declare(strict_types=1);

use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Http\Controllers\ChatController;
use App\Modules\Assistant\Support\HumiTokens;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Billing\Models\UsageReservation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

mutates(ChatController::class, HumiTokens::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->a, TenantRole::Hr);
    config(['humi.monthly_token_budget' => 100_000_000, 'humi.rate_per_minute' => 100, 'humi.rate_per_day' => 1000, 'humi.tenant_rate_per_minute' => 1000, 'humi.reservation_tokens' => 9000]);
});

function ledgerCounter(Tenant $tenant): ?UsageCounter
{
    return UsageCounter::query()->where('tenant_id', $tenant->id)->first();
}

function sendToHumi(string $message = 'Hola'): Illuminate\Testing\TestResponse
{
    return chat(['message' => $message]);
}

it('reserves before the model is called and settles with the tokens the SDK reports, leaving nothing held', function (): void {
    HumiAssistant::fake(['Tu campaña va bien.']);

    sendToHumi()->assertOk()->streamedContent();

    $counter = ledgerCounter($this->a);
    $event = UsageEvent::query()->sole();

    expect($counter->reserved)->toBe(0)
        ->and($counter->used)->toBe($event->quantity)
        ->and(UsageReservation::query()->sole()->state)->toBe('settled')
        ->and($event->tenant_id)->toBe($this->a->id)
        ->and($event->meter)->toBe('assistant_tokens');
});

it('answers 403 with a clear message and never calls the model when the quota of the month cannot hold the message', function (): void {
    config(['billing.unplanned_quotas.assistant_tokens' => 8999]);
    HumiAssistant::fake(['No debería salir.']);

    sendToHumi()->assertForbidden()->assertJson(['reason' => 'budget', 'message' => 'Tu empresa agotó el uso de Humi de este mes. Se renueva el primer día del mes.']);

    HumiAssistant::assertNeverPrompted();
    expect(UsageReservation::query()->count())->toBe(0);
});

it('holds exactly the quota: 9,000 free tokens answer one of two messages and refuse the other', function (): void {
    config(['billing.unplanned_quotas.assistant_tokens' => 9000]);
    HumiAssistant::fake(['Uno.', 'Dos.']);
    $meter = resolve(App\Modules\Billing\Contracts\UsageMeter::class);

    // The first message is still being answered: its hold is on the quota when the second arrives.
    $meter->reserve($this->a->id, 'assistant_tokens', 9000, 'in-flight');

    sendToHumi()->assertForbidden();
    $meter->release($this->a->id, 'in-flight');
    sendToHumi()->assertOk()->streamedContent();

    expect(UsageEvent::query()->count())->toBe(1);
});

it('does not touch the rest of the product when Humi is out of quota', function (): void {
    config(['billing.unplanned_quotas.assistant_tokens' => 1]);

    sendToHumi()->assertForbidden();

    $this->actingAs($this->hr)->get(route('tenant.dashboard', $this->a))->assertOk();
});

it('releases the hold when the answer fails, so the quota is not lost', function (): void {
    HumiAssistant::fake([fn () => throw new RuntimeException('el proveedor cayó')]);

    try {
        sendToHumi()->streamedContent();
    } catch (Throwable) {
        // The stream ends in the failure; what matters is what is left held.
    }

    expect(UsageReservation::query()->sole()->state)->toBe('released')
        ->and(ledgerCounter($this->a)->only(['used', 'reserved']))->toBe(['used' => 0, 'reserved' => 0])
        ->and(UsageEvent::query()->count())->toBe(0);
});

it('counts each company\'s messages on its own quota', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    $user = memberOf($other, TenantRole::Hr);
    HumiAssistant::fake(['Uno.', 'Dos.']);

    sendToHumi()->streamedContent();
    chat(['message' => 'Hola'], $user, $other)->streamedContent();

    expect(UsageCounter::query()->where('tenant_id', $this->a->id)->count())->toBe(1)
        ->and(UsageCounter::query()->where('tenant_id', $other->id)->count())->toBe(1)
        ->and(UsageEvent::query()->count())->toBe(2);
});

it('keeps what was asked and answered out of the ledger and out of the logs', function (): void {
    HumiAssistant::fake(['La respuesta secreta-xyzzy.']);
    $lines = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines): void {
        $lines[] = $event->message.json_encode($event->context, JSON_UNESCAPED_UNICODE);
    });

    sendToHumi('Pregunta secreta-plugh')->streamedContent();

    $stored = collect(['usage_counters', 'usage_reservations', 'usage_events'])
        ->map(fn (string $table): string => json_encode(Illuminate\Support\Facades\DB::table($table)->get(), JSON_UNESCAPED_UNICODE))
        ->implode("\n");

    expect(implode("\n", $lines))->toContain('usage.settled')->not->toContain('secreta-plugh')->not->toContain('secreta-xyzzy')
        ->and($stored)->not->toContain('secreta')
        ->and(Schema::getColumnListing('usage_events'))->toBe(['id', 'tenant_id', 'meter', 'period_start', 'quantity', 'idempotency_key', 'occurred_at'])
        ->and(Schema::getColumnListing('usage_reservations'))->toBe(['id', 'tenant_id', 'meter', 'period_start', 'idempotency_key', 'quantity', 'state', 'expires_at', 'created_at', 'updated_at']);
});
