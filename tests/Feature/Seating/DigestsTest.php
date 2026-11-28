<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\SendSeatingDigests;
use App\Modules\Seating\Models\SeatingDigestDelivery;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Notifications\SeatingDigest;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Seating\PositionAssignmentFactory;
use Database\Factories\Seating\RiskAssessmentFactory;
use Database\Factories\Seating\SeatFactory;
use Database\Factories\Seating\SeatReportFactory;
use Database\Factories\Seating\StandingPositionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
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
    StandingPosition::query()->delete();

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

it('tells the owner of all seven things that wait and a Comisión member of the five that are theirs, in that order', function (): void {
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id];
    $position = StandingPositionFactory::new()->nearby()->create($where);
    PositionAssignmentFactory::new()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id]);
    $assessed = PositionAssignmentFactory::new()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id]);
    RiskAssessmentFactory::new()->signed()->create($where + ['employee_id' => $assessed->employee_id, 'position_id' => $position->id, 'reassessment_due' => true]);
    SeatReportFactory::new()->create($where + ['created_at' => now()->subDays(20)]);
    SeatFactory::new()->restArea()->create($where);

    resolve(SendSeatingDigests::class)->handle();

    expect(digestLinesTo($this->owner->email))->toBe([
        'Esto espera tu atención en Acme:',
        '1 persona que trabaja de pie sin evaluar.',
        '1 evaluación por repetir.',
        '1 reporte de asiento abierto hace más de 15 días.',
        '1 puesto sin acuerdo de pausas.',
        '2 reglamentos sin las tres partes o sin depósito.',
        '1 asiento fuera del puesto sin señalizar.',
        '1 centro sin integrantes en la Comisión.',
    ])->and(digestLinesTo($this->cora->email))->toBe([
        'Esto espera tu atención en Acme:',
        '1 persona que trabaja de pie sin evaluar.',
        '1 evaluación por repetir.',
        '1 reporte de asiento abierto hace más de 15 días.',
        '1 puesto sin acuerdo de pausas.',
        '1 asiento fuera del puesto sin señalizar.',
    ]);
});

it('leaves the demo company out, and tells the others even when one of them fails', function (): void {
    Exceptions::fake();
    // The demo goes first and the broken company second, so neither keeps the one after them from its digest.
    $demo = Tenant::factory()->demo()->create(['id' => '00000000-0000-4000-8000-000000000001', 'slug' => 'demo']);
    $broken = Tenant::factory()->create(['id' => '00000000-0000-4000-8000-000000000002', 'slug' => 'beta']);
    foreach ([$demo, $broken] as $company) {
        ownerOf($company);
        $center = WorkCenter::factory()->for($company)->create();
        StandingPositionFactory::new()->create(['tenant_id' => $company->id, 'work_center_id' => $center->id]);
        SeatReportFactory::new()->create(['tenant_id' => $company->id, 'work_center_id' => $center->id, 'created_at' => now()->subDays(20)]);
    }
    $brokenCenter = WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $broken->id)->value('id');
    StandingPosition::retrieved(function (StandingPosition $position) use ($brokenCenter): void {
        if ($position->getAttributes() === ['work_center_id' => $brokenCenter]) {
            throw new RuntimeException('boom');
        }
    });

    resolve(SendSeatingDigests::class)->handle();

    Exceptions::assertReported(fn (RuntimeException $failure): bool => $failure->getMessage() === 'boom');
    Notification::assertSentOnDemandTimes(SeatingDigest::class, 1);
    expect(digestLinesTo($this->owner->email))->toContain('2 reglamentos sin las tres partes o sin depósito.');
});
