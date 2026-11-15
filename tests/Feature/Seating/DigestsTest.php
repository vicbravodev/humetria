<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\SendSeatingDigests;
use App\Modules\Seating\Models\SeatingDigestDelivery;
use App\Modules\Seating\Notifications\SeatingDigest;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Seating\SeatReportFactory;
use Database\Factories\Seating\StandingPositionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendSeatingDigests::class, SeatingDigest::class);

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-03-01 14:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->owner = ownerOf($this->tenant);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->south->id]);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $this->cora->id]);
});

/** @return list<string> */
function digestLinesTo(string $email): array
{
    $lines = [];
    Notification::assertSentOnDemand(SeatingDigest::class, function (SeatingDigest $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$lines): bool {
        if ($notifiable->routes['mail'] !== $email) {
            return false;
        }
        $lines = $notification->toMail($notifiable)->introLines;

        return true;
    });

    return $lines;
}

it('sends the owner one digest with what waits across the company, in counts only', function (): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'description' => 'Texto privado', 'created_at' => now()->subDays(20)]);
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->south->id, 'created_at' => now()->subDays(20)]);

    $sent = resolve(SendSeatingDigests::class)->handle();

    $lines = digestLinesTo($this->owner->email);
    expect($sent)->toBeGreaterThanOrEqual(1)
        ->and(implode(' ', $lines))->toContain('2 reportes de asiento')->toContain('reglamento')->not->toContain('Texto privado');
});

it('sends each Comisión member the digest of the centers they sit on, not the rest', function (): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'created_at' => now()->subDays(20)]);
    SeatReportFactory::new()->count(3)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->south->id, 'created_at' => now()->subDays(20)]);

    resolve(SendSeatingDigests::class)->handle();

    expect(implode(' ', digestLinesTo($this->cora->email)))->toContain('1 reporte de asiento')->not->toContain('3 reportes')->not->toContain('4 reportes');
});

it('sends one digest per recipient per day, and again the next day', function (): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'created_at' => now()->subDays(20)]);

    $first = resolve(SendSeatingDigests::class)->handle();
    $again = resolve(SendSeatingDigests::class)->handle();

    expect($again)->toBe(0)->and(SeatingDigestDelivery::query()->count())->toBe($first);
    Notification::assertSentOnDemandTimes(SeatingDigest::class, $first);

    $this->travelTo('2026-03-02 14:00:00');
    expect(resolve(SendSeatingDigests::class)->handle())->toBe($first);
});

it('sends nothing when there is nothing to say', function (): void {
    // The center has its Comisión, a signed assessment, the regulation and no report or seat: only the regulation and the
    // assessments could speak, so make them quiet.
    App\Modules\Seating\Models\StandingPosition::query()->delete();

    expect(resolve(SendSeatingDigests::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('sends nothing for a company that may not be used and never mixes companies', function (): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'created_at' => now()->subDays(20)]);
    $blocked = Tenant::factory()->suspended()->create();
    $blockedOwner = ownerOf($blocked);
    $center = WorkCenter::factory()->for($blocked)->create();
    StandingPositionFactory::new()->create(['tenant_id' => $blocked->id, 'work_center_id' => $center->id]);
    workIn($this->tenant);

    resolve(SendSeatingDigests::class)->handle();

    Notification::assertNotSentTo(new AnonymousNotifiable, SeatingDigest::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $blockedOwner->email);
    expect(implode(' ', digestLinesTo($this->owner->email)))->not->toContain('2 reportes');
});

it('leads to the one center when the digest is about one, and to the list otherwise', function (): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'created_at' => now()->subDays(20)]);

    resolve(SendSeatingDigests::class)->handle();

    $urls = [];
    Notification::assertSentOnDemand(SeatingDigest::class, function (SeatingDigest $notification, array $channels, AnonymousNotifiable $notifiable) use (&$urls): bool {
        $urls[$notifiable->routes['mail']] = $notification->toMail($notifiable)->actionUrl;

        return true;
    });
    expect($urls[$this->cora->email])->toBe(route('tenant.seating.center', ['acme', $this->north->id]))->and($urls[$this->owner->email])->toBe(route('tenant.seating.index', 'acme'));
});

it('logs how many digests went, as counts', function (): void {
    Log::spy();

    $sent = resolve(SendSeatingDigests::class)->handle();

    Log::shouldHaveReceived('info')->with('seating.digests_sent', ['owner' => 1, 'committee' => $sent - 1])->once();
});

it('refuses in the database a second delivery to the same recipient the same day', function (): void {
    SeatingDigestDelivery::factory()->create(['tenant_id' => $this->tenant->id, 'recipient' => 'owner', 'day' => '2026-03-01']);

    expect(fn () => DB::transaction(fn () => SeatingDigestDelivery::factory()->create(['tenant_id' => $this->tenant->id, 'recipient' => 'owner', 'day' => '2026-03-01'])))
        ->toThrow(QueryException::class, 'seating_digest_deliveries_unique');
});
