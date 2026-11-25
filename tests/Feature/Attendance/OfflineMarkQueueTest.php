<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Http\Controllers\OfflineMarkController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Attendance\Support\OfflineMarkPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\MarkReviewFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\OfflineDeliveries;

mutates(OfflineMarkController::class, OfflineMarkPages::class);

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function offlineUrl(array $query = []): string
{
    return route('tenant.attendance.corrections.index', ['acme', 'status' => 'offline', ...$query]);
}

function rejectedUrl(array $query = []): string
{
    return route('tenant.attendance.corrections.index', ['acme', 'status' => 'not_accepted', ...$query]);
}

/** Queries a request ran, once the first visit warmed what the session and the permissions keep. */
function offlineQueueQueries(string $url): int
{
    test()->get($url)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/** A person of the plant with a name. */
function personNamed(string $name): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->plant->id, 'name' => $name, 'job_title' => 'Operador']);
}

describe('the tab of marks made without a connection', function (): void {
    it('lists the marks to verify with the person and the times in local time, flagged first and then the oldest', function (): void {
        $calm = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', receivedAt: CarbonImmutable::parse('2026-11-09 11:00', 'America/Mexico_City'));
        $flagged = OfflineDeliveries::accepted($this->ana, '2026-11-10 18:00', MarkType::End, qr: true, flags: [ReviewFlag::NoPosition, ReviewFlag::OutsideZone], receivedAt: CarbonImmutable::parse('2026-11-10 20:00', 'America/Mexico_City'));
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/corrections')
            ->where('status', 'offline')
            ->where('filters', ['q' => '', 'flags' => ''])
            ->where('counts', ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'offline' => 2, 'not_accepted' => 0])
            ->where('canResolve', true)
            ->missing('requests')
            ->where('offlineMarks.total', 2)
            ->where('offlineMarks.per_page', 25)
            ->where('offlineMarks.data.0', [
                'id' => $flagged->mark_id,
                'person' => ['name' => 'Ana López', 'jobTitle' => 'Operadora'],
                'type' => 'end',
                'countedAt' => '2026-11-10 18:00',
                'basis' => 'qr_window',
                'flags' => ['no_position', 'outside_zone'],
                'ageDays' => 1,
            ])
            ->where('offlineMarks.data.1.id', $calm->mark_id)
            ->where('offlineMarks.data.1.flags', [])
            ->where('offlineMarks.data.1.basis', 'declared_interval')
            ->where('offlineMarks.data.1.ageDays', 2));
    });

    it('lists the mark of a center with no zones with its own flag, among the flagged', function (): void {
        $calm = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', receivedAt: CarbonImmutable::parse('2026-11-09 11:00', 'America/Mexico_City'));
        $noZones = OfflineDeliveries::accepted($this->ana, '2026-11-10 09:00', MarkType::End, flags: [ReviewFlag::ZonesNotConfigured], receivedAt: CarbonImmutable::parse('2026-11-10 11:00', 'America/Mexico_City'));
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('offlineMarks.data.0.id', $noZones->mark_id)->where('offlineMarks.data.0.flags', ['zones_not_configured'])
            ->where('offlineMarks.data.1.id', $calm->mark_id)->where('offlineMarks.data.1.flags', []));
    });

    it('puts the oldest first among those with the same flags', function (): void {
        $newer = OfflineDeliveries::accepted($this->ana, '2026-11-10 09:00', receivedAt: CarbonImmutable::parse('2026-11-10 11:00', 'America/Mexico_City'));
        $older = OfflineDeliveries::accepted($this->ana, '2026-11-05 09:00', receivedAt: CarbonImmutable::parse('2026-11-05 11:00', 'America/Mexico_City'));
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.data.0.id', $older->mark_id)->where('offlineMarks.data.1.id', $newer->mark_id));
    });

    it('leaves only the marks with no flags when asked, to verify them together', function (): void {
        OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', flags: [ReviewFlag::WideInterval]);
        $calm = OfflineDeliveries::accepted($this->ana, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->get(offlineUrl(['flags' => 'none']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => '', 'flags' => 'none'])
            ->where('offlineMarks.total', 1)->where('offlineMarks.data.0.id', $calm->mark_id)
            ->where('counts.offline', 2));
    });

    it('leaves out the marks already reviewed, the ones the person or RH corrected and the ones made online', function (): void {
        $pending = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $verified = OfflineDeliveries::accepted($this->ana, '2026-11-10 09:00');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $verified->mark_id]);
        $corrected = OfflineDeliveries::accepted($this->ana, '2026-11-08 09:00');
        AttendanceMark::factory()->correcting(AttendanceMark::query()->findOrFail($corrected->mark_id))->create();
        AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'marked_at' => CarbonImmutable::parse('2026-11-07 09:00', 'America/Mexico_City')->utc()]);
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('offlineMarks.total', 1)->where('offlineMarks.data.0.id', $pending->mark_id)->where('counts.offline', 1));
    });

    it('has nothing in view when every mark was reviewed', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $delivery->mark_id]);
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 0)->where('selectedId', null)->where('detail', null));
    });

    it('opens the first mark of the page, or the one asked for, with every hour named', function (): void {
        $first = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', qr: true, flags: [ReviewFlag::DeclaredAdjusted], receivedAt: CarbonImmutable::parse('2026-11-09 19:00', 'America/Mexico_City'));
        $second = OfflineDeliveries::accepted($this->ana, '2026-11-10 09:00', receivedAt: CarbonImmutable::parse('2026-11-10 19:00', 'America/Mexico_City'));
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedId', $first->mark_id)
            ->where('detail.id', $first->mark_id)
            ->where('detail.declaredAt', '2026-11-09 09:00')
            ->where('detail.ticketIssuedAt', '2026-11-09 08:00')
            ->where('detail.windowStart', '2026-11-09 08:55')
            ->where('detail.receivedAt', '2026-11-09 19:00')
            ->where('detail.phoneClockAt', null)
            ->where('detail.signer', 'passkey')
            ->where('detail.proposedAt', '2026-11-09 09:00'));
        $this->get(offlineUrl(['request' => $second->mark_id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedId', $second->mark_id)->where('detail.windowStart', null)->where('detail.signer', 'passkey'));
    });

    it('proposes the time the person declared when the counted one differs', function (): void {
        $counted = AttendanceMark::factory()->offlineDeclared()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id,
            'marked_at' => CarbonImmutable::parse('2026-11-09 10:00', 'America/Mexico_City')->utc(),
        ]);
        OfflineDeliveries::accepted($this->ana, '2026-11-09 12:30', flags: [ReviewFlag::DeclaredAdjusted], mark: $counted);
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('offlineMarks.data.0.countedAt', '2026-11-09 10:00')->where('detail.declaredAt', '2026-11-09 12:30')->where('detail.proposedAt', '2026-11-09 12:30'));
    });

    it('falls to the first mark when the one asked for is unknown, of another company or not an id', function (): void {
        $first = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $globex = Tenant::factory()->create();
        workIn($globex);
        $foreign = OfflineDeliveries::accepted(EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => WorkCenter::factory()->for($globex)->create()->id]), '2026-11-09 09:00');
        workIn($this->tenant);
        $this->actingAs($this->hr);

        foreach ([$foreign->mark_id, '0b8a3a4e-0000-4000-8000-000000000000', 'ana'] as $other) {
            $this->get(offlineUrl(['request' => $other]))->assertInertia(fn (AssertableInertia $page) => $page->where('selectedId', $first->mark_id));
        }
    });

    it('pages the marks 25 at a time and searches by the person\'s name', function (): void {
        $bob = personNamed('Roberto Díaz');
        foreach (range(1, 26) as $n) {
            OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00', receivedAt: CarbonImmutable::parse('2026-11-02 11:00', 'America/Mexico_City')->addMinutes($n));
        }
        OfflineDeliveries::accepted($bob, '2026-11-03 09:00');
        $this->actingAs($this->hr);

        $this->get(offlineUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 27)->has('offlineMarks.data', 2)->where('offlineMarks.current_page', 2));
        $this->get(offlineUrl(['q' => 'DIAZ']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => 'DIAZ', 'flags' => ''])->where('offlineMarks.total', 1)->where('offlineMarks.data.0.person.name', 'Roberto Díaz'));
    });

    it('reads the same queries with one mark and with thirty', function (): void {
        OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);
        $one = offlineQueueQueries(offlineUrl());

        foreach (range(1, 29) as $n) {
            OfflineDeliveries::accepted(personNamed("Persona {$n}"), '2026-11-09 09:00', flags: $n % 2 === 0 ? [ReviewFlag::NoPosition] : []);
        }

        expect(offlineQueueQueries(offlineUrl()))->toBe($one);
    });

    it('never sends a position, a key, a ticket, a QR, a challenge or a device', function (): void {
        OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', qr: true, flags: [ReviewFlag::NoPosition]);
        $this->actingAs($this->hr);

        $json = (string) $this->get(offlineUrl())->assertOk()->getContent();

        expect($json)->not->toContain('latitude')->not->toContain('longitude')->not->toContain('credential_id')->not->toContain('signer_id')
            ->not->toContain('ticket_nonce')->not->toContain('challenge')->not->toContain('assertion')->not->toContain('device_clock_at')->not->toContain('accuracy');
    });

    it('lets RH read the note of the person and hides it from a viewer: free text that may be medical', function (): void {
        OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00', justification: 'Estuve en urgencias con mi hijo');
        $this->actingAs($this->hr);
        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('detail.justification', 'Estuve en urgencias con mi hijo'));

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
        $response = $this->get(offlineUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canResolve', false)->where('detail.justification', null)->where('offlineMarks.total', 1));

        expect((string) $response->getContent())->not->toContain('urgencias');
    });

    it('never lists the marks of another company', function (): void {
        $globex = Tenant::factory()->create();
        workIn($globex);
        OfflineDeliveries::accepted(EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => WorkCenter::factory()->for($globex)->create()->id]), '2026-11-09 09:00');
        OfflineDeliveries::rejected(EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => WorkCenter::factory()->for($globex)->create()->id]), '2026-11-09 09:00', OfflineRejection::TicketExpired);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 0)->where('counts.offline', 0)->where('counts.not_accepted', 0));
    });
});

describe('the tab of deliveries that were not accepted', function (): void {
    it('lists the rejected deliveries of the last 35 days with their reason, newest first, read-only', function (): void {
        $older = OfflineDeliveries::rejected($this->ana, '2026-10-20 09:00', OfflineRejection::SequenceConflict, CarbonImmutable::parse('2026-10-20 11:00', 'America/Mexico_City'));
        $newer = OfflineDeliveries::rejected($this->ana, '2026-11-10 18:00', OfflineRejection::WrongCenter, CarbonImmutable::parse('2026-11-10 20:00', 'America/Mexico_City'));
        // 36 days before the 11th of November at noon: out.
        OfflineDeliveries::rejected($this->ana, '2026-10-05 09:00', OfflineRejection::PassExpired, CarbonImmutable::parse('2026-10-06 11:00', 'America/Mexico_City'));
        OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);

        $this->get(rejectedUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/corrections')
            ->where('status', 'not_accepted')
            ->where('counts.not_accepted', 2)->where('counts.offline', 1)
            ->missing('requests')->where('detail', null)
            ->where('offlineMarks.total', 2)
            ->where('offlineMarks.data.0', [
                'id' => $newer->id,
                'person' => ['name' => 'Ana López', 'jobTitle' => 'Operadora'],
                'type' => 'start',
                'declaredAt' => '2026-11-10 18:00',
                'reason' => 'wrong_center',
                'receivedAt' => '2026-11-10 20:00',
            ])
            ->where('offlineMarks.data.1.id', $older->id)->where('offlineMarks.data.1.reason', 'sequence_conflict'));
    });

    it('counts the delivery that arrived exactly 35 days ago and not one more day', function (): void {
        OfflineDeliveries::rejected($this->ana, '2026-10-07 09:00', OfflineRejection::TicketExpired, now()->subDays(35)->addMinute());
        OfflineDeliveries::rejected($this->ana, '2026-10-06 09:00', OfflineRejection::TicketExpired, now()->subDays(35)->subMinute());
        $this->actingAs($this->hr);

        $this->get(rejectedUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 1)->where('counts.not_accepted', 1));
    });

    it('pages them 25 at a time, searches by name and reads the same queries with one and with thirty', function (): void {
        $bob = personNamed('Roberto Díaz');
        OfflineDeliveries::rejected($this->ana, '2026-11-09 09:00', OfflineRejection::PassExpired);
        $this->actingAs($this->hr);
        $one = offlineQueueQueries(rejectedUrl());

        foreach (range(1, 26) as $n) {
            OfflineDeliveries::rejected(personNamed("Persona {$n}"), '2026-11-09 09:00', OfflineRejection::PersonInactive);
        }
        OfflineDeliveries::rejected($bob, '2026-11-09 09:00', OfflineRejection::TicketExpired);

        expect(offlineQueueQueries(rejectedUrl()))->toBe($one);
        $this->get(rejectedUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 28)->has('offlineMarks.data', 3));
        $this->get(rejectedUrl(['q' => 'diaz']))->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 1));
    });

    it('lets a viewer read it, and never sends what the delivery signed', function (): void {
        OfflineDeliveries::rejected($this->ana, '2026-11-09 09:00', OfflineRejection::PassExpired);
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $json = (string) $this->get(rejectedUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canResolve', false)->where('offlineMarks.total', 1))->getContent();

        expect($json)->not->toContain('signer_id')->not->toContain('ticket_nonce')->not->toContain('challenge')->not->toContain('assertion')->not->toContain('latitude');
    });
});

describe('who opens and who resolves', function (): void {
    it('keeps the committee out of both tabs', function (string $url): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));

        $this->get($url)->assertForbidden();
    })->with(['offline' => fn () => offlineUrl(), 'not accepted' => fn () => rejectedUrl()]);

    it('answers 404 to a union account', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(route('tenant.attendance.corrections.index', [$union->slug, 'status' => 'offline']))->assertNotFound();
    });

    it('keeps a viewer from verifying, marking incorrect or verifying several', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.attendance.offline.verify', ['acme', $delivery->mark_id]))->assertForbidden();
        $this->post(route('tenant.attendance.offline.reject', ['acme', $delivery->mark_id]), ['note' => 'Entró a las 9:20.'])->assertForbidden();
        $this->post(route('tenant.attendance.offline.verify-many', ['acme']), ['ids' => [$delivery->mark_id]])->assertForbidden();

        expect(MarkReview::query()->count())->toBe(0);
    });

    it('lets owner and hr resolve', function (TenantRole $role): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs(memberOf($this->tenant, $role))->from(offlineUrl())
            ->post(route('tenant.attendance.offline.verify', ['acme', $delivery->mark_id]))->assertRedirect(offlineUrl());

        expect(MarkReview::query()->count())->toBe(1);
    })->with([TenantRole::Owner, TenantRole::Hr]);
});

describe('verifying', function (): void {
    it('verifies one mark with a toast, back to the tab, and the mark leaves the list', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr)->from(offlineUrl());

        $this->post(route('tenant.attendance.offline.verify', ['acme', $delivery->mark_id]))
            ->assertRedirect(offlineUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Marca verificada.']);

        expect(MarkReview::query()->sole())->decision->toBe(ReviewDecision::Verified)->reviewer_id->toBe($this->hr->id);
        $this->get(offlineUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('offlineMarks.total', 0)->where('counts.offline', 0));
    });

    it('marks one incorrect with the time RH writes and the note the person will read', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr)->from(offlineUrl());

        $this->post(route('tenant.attendance.offline.reject', ['acme', $delivery->mark_id]), ['note' => 'Entró a las 9:20, lo dice la cámara.', 'corrected_at' => '2026-11-09 09:20'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Marca corregida. La persona verá tu nota.']);

        $correction = AttendanceMark::query()->where('corrects_mark_id', $delivery->mark_id)->sole();
        expect($correction->marked_at?->toIso8601String())->toBe('2026-11-09T15:20:00+00:00')->and($correction->reason)->toBe('Entró a las 9:20, lo dice la cámara.');
    });

    it('voids the mark when RH writes no time', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.offline.reject', ['acme', $delivery->mark_id]), ['note' => 'No estuvo ese día.', 'corrected_at' => null])->assertSessionHasNoErrors();

        expect(AttendanceMark::query()->where('corrects_mark_id', $delivery->mark_id)->sole()->marked_at)->toBeNull();
    });

    it('verifies the selected marks and says how many', function (): void {
        $ids = array_map(fn (string $day): string => OfflineDeliveries::accepted($this->ana, "2026-11-0{$day} 09:00")->mark_id ?? '', ['2', '3', '4']);
        $this->actingAs($this->hr)->from(offlineUrl());

        $this->post(route('tenant.attendance.offline.verify-many', ['acme']), ['ids' => $ids])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Verificaste 3 marcas.']);

        expect(MarkReview::query()->count())->toBe(3);
    });

    it('says how many changed since the list was opened', function (): void {
        $ids = array_map(fn (string $day): string => OfflineDeliveries::accepted($this->ana, "2026-11-0{$day} 09:00")->mark_id ?? '', ['2', '3']);
        MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $ids[1]]);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.offline.verify-many', ['acme']), ['ids' => $ids])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Verificaste 1 marca. 1 cambió desde que abriste la lista: revísala una por una.']);
    });

    it('says it in the plural when several changed', function (): void {
        $ids = array_map(fn (string $day): string => OfflineDeliveries::accepted($this->ana, "2026-11-0{$day} 09:00")->mark_id ?? '', ['2', '3', '4']);
        foreach (array_slice($ids, 1) as $id) {
            MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $id]);
        }
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.offline.verify-many', ['acme']), ['ids' => $ids])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Verificaste 1 marca. 2 cambiaron desde que abriste la lista: revísalas una por una.']);
    });

    it('answers each refusal with its toast', function (string $case, string $message): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);

        if ($case === 'corrected') {
            AttendanceMark::factory()->correcting(AttendanceMark::query()->findOrFail($delivery->mark_id))->create();
        }

        if ($case === 'order') {
            AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::End, 'marked_at' => CarbonImmutable::parse('2026-11-09 18:00', 'America/Mexico_City')->utc()]);
        }

        $mark = $case === 'online'
            ? AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'marked_at' => CarbonImmutable::parse('2026-11-07 09:00', 'America/Mexico_City')->utc()])->id
            : $delivery->mark_id;
        $payload = $case === 'order' ? ['note' => 'Entró a las 19:00.', 'corrected_at' => '2026-11-09 19:00'] : [];

        $this->post(route('tenant.attendance.offline.'.($case === 'order' ? 'reject' : 'verify'), ['acme', $mark]), $payload)
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => $message]);
    })->with([
        'a mark corrected meanwhile' => ['corrected', 'La marca ya se corrigió o anuló desde que la abriste.'],
        'a mark made online' => ['online', 'Esa marca no se hizo sin conexión.'],
        'a time that leaves the day out of order' => ['order', 'Con esa hora el día queda en desorden. Escribe otra hora o deja la marca anulada.'],
    ]);

    it('refuses a time that has not come yet', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.offline.reject', ['acme', $delivery->mark_id]), ['note' => 'Entró más tarde.', 'corrected_at' => '2026-11-11 13:00'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esa hora aún no llega. Revisa la hora y el día.']);
    });

    it('answers 404 to the mark of another company', function (): void {
        $globex = Tenant::factory()->create();
        workIn($globex);
        $foreign = OfflineDeliveries::accepted(EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => WorkCenter::factory()->for($globex)->create()->id]), '2026-11-09 09:00');
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.offline.verify', ['acme', $foreign->mark_id]))->assertNotFound();
        $this->post(route('tenant.attendance.offline.reject', ['acme', $foreign->mark_id]), ['note' => 'Entró a las 9:20.'])->assertNotFound();
        expect(MarkReview::query()->count())->toBe(0);
    });

    it('validates the note, the time and the list of marks', function (string $route, array $payload, string $field): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-09 09:00');
        $this->actingAs($this->hr);
        $url = $route === 'verify-many' ? route('tenant.attendance.offline.verify-many', ['acme']) : route("tenant.attendance.offline.{$route}", ['acme', $delivery->mark_id]);

        $this->post($url, $payload)->assertSessionHasErrors($field);

        expect(MarkReview::query()->count())->toBe(0);
    })->with([
        'a note of 4 characters' => ['reject', ['note' => 'Cuat'], 'note'],
        'no note when marking incorrect' => ['reject', [], 'note'],
        'a time badly written' => ['reject', ['note' => 'Entró a las 9:20.', 'corrected_at' => '9:20'], 'corrected_at'],
        'a note of 4 characters when verifying' => ['verify', ['note' => 'Cuat'], 'note'],
        'an empty list' => ['verify-many', ['ids' => []], 'ids'],
        'no list' => ['verify-many', [], 'ids'],
        'an id that is not a uuid' => ['verify-many', ['ids' => ['ana']], 'ids.0'],
        'a repeated id' => ['verify-many', ['ids' => ['9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c', '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']], 'ids.0'],
        'more than 25 marks' => ['verify-many', ['ids' => array_map(fn (int $n): string => sprintf('9f6c1c55-7d3e-4f43-9c1e-%012d', $n), range(1, 26))], 'ids'],
    ]);
});
