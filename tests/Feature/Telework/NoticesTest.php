<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\ReviewWorkplace;
use App\Modules\Telework\Actions\SubmitNotice;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Http\Controllers\PortalNoticeController;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SubmitNotice::class, PortalNoticeController::class, ReviewWorkplace::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    $this->teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    workIn($this->tenant);
});

function noticeToken(): string
{
    return resolve(IssuePortalLink::class)->handle(test()->employee->id);
}

/** @return array<string, mixed> */
function noticeForm(array $overrides = []): array
{
    return ['kind' => 'work_risk', 'written_on' => '2026-02-27', 'occurred_on' => '2026-02-25', 'description' => 'Me caí de la silla trabajando.', ...$overrides];
}

function noticeFor(NoticeKind $kind, array $overrides = []): WorkerNotice
{
    $portal = resolve(OpenPortal::class)->handle(noticeToken());

    return resolve(SubmitNotice::class)->handle(
        $portal,
        $kind,
        CarbonImmutable::parse($overrides['written_on'] ?? '2026-02-27'),
        CarbonImmutable::parse($overrides['occurred_on'] ?? '2026-02-25'),
        $overrides['description'] ?? 'Descripción del aviso',
        $overrides['traumatic'] ?? false,
        null,
        $overrides['temporary'] ?? false,
        '203.0.113.9',
    );
}

it('requires written date, occurrence date and description for each kind', function (string $kind, string $field): void {
    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['kind' => $kind, $field => '']))->assertSessionHasErrors($field);

    expect(WorkerNotice::query()->count())->toBe(0);
})->with(fn () => collect(['condition_change', 'work_risk'])->crossJoin(['written_on', 'occurred_on', 'description'])->all());

it('stores each notice with its dates, time and address, and logs only ids', function (string $kind): void {
    Log::spy();
    $token = noticeToken();

    $this->post(route('portal.notices.store', $token), noticeForm(['kind' => $kind, 'description' => '  Texto privado del aviso  ']))
        ->assertSessionHasNoErrors()->assertRedirect(route('portal.notices.show', $token));

    $notice = WorkerNotice::query()->sole();
    expect($notice->kind->value)->toBe($kind)
        ->and($notice->teleworker_id)->toBe($this->teleworker->id)
        ->and($notice->written_on->toDateString())->toBe('2026-02-27')
        ->and($notice->occurred_on->toDateString())->toBe('2026-02-25')
        ->and($notice->description)->toBe('Texto privado del aviso')
        ->and($notice->traumatic_event)->toBeFalse()
        ->and($notice->temporary)->toBeFalse()
        ->and($notice->submitted_at->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
        ->and($notice->ip)->toBe('127.0.0.1')
        ->and($notice->reviewed_at)->toBeNull();
    Log::shouldHaveReceived('info')->with('telework.notice_submitted', ['notice_id' => $notice->id, 'teleworker_id' => $this->teleworker->id, 'kind' => $kind, 'traumatic_event' => false, 'temporary' => false])->once();
})->with(['condition_change', 'work_risk']);

it('never logs the description', function (): void {
    Log::spy();

    noticeFor(NoticeKind::WorkRisk, ['description' => 'Secreto-de-la-descripcion']);

    Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Secreto'));
    Log::shouldNotHaveReceived('warning');
});

it('accepts today and an occurrence before the written date, and refuses the future', function (): void {
    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['written_on' => '2026-03-01', 'occurred_on' => '2026-01-10']))->assertSessionHasNoErrors();
    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['written_on' => '2026-03-02']))->assertSessionHasErrors('written_on');
    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['occurred_on' => '2026-03-02']))->assertSessionHasErrors('occurred_on');

    expect(WorkerNotice::query()->count())->toBe(1);
});

it('judges the day in Mexico City, not UTC', function (): void {
    $this->travelTo('2026-03-02 03:00:00');

    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['written_on' => '2026-03-01']))->assertSessionHasNoErrors();
    $this->post(route('portal.notices.store', noticeToken()), noticeForm(['written_on' => '2026-03-02']))->assertSessionHasErrors('written_on');
});

it('validates the action itself', function (array $override): void {
    expect(fn () => noticeFor(NoticeKind::WorkRisk, $override))->toThrow(ValidationException::class);
    expect(WorkerNotice::query()->count())->toBe(0);
})->with([
    'future written' => [['written_on' => '2026-03-02']],
    'future occurred' => [['occurred_on' => '2026-03-02']],
    'blank description' => [['description' => '   ']],
    'traumatic on a risk' => [['traumatic' => true]],
    'temporary on a risk' => [['temporary' => true]],
]);

it('refuses in the database a traumatic event outside a condition change and a temporary outside an address change', function (): void {
    $blocked = fn (array $state) => expect(fn () => DB::transaction(fn () => WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, ...$state])))->toThrow(QueryException::class);

    $blocked(['kind' => 'work_risk', 'traumatic_event' => true]);
    $blocked(['kind' => 'condition_change', 'temporary' => true]);
    $blocked(['kind' => 'address_change']);
    $blocked(['kind' => 'work_risk', 'description' => ' ']);
    $blocked(['reviewed_at' => now()]);
    expect(WorkerNotice::query()->count())->toBe(0);
});

it('keeps one company\'s notices out of another\'s queries', function (): void {
    noticeFor(NoticeKind::WorkRisk);
    workIn(Tenant::factory()->create());

    expect(WorkerNotice::query()->count())->toBe(0);
    workIn($this->tenant);
    expect(WorkerNotice::query()->count())->toBe(1);
});

it('lists a person\'s sent notices without any review content', function (): void {
    $received = noticeFor(NoticeKind::WorkRisk, ['description' => 'Sin revisar']);
    $reviewed = noticeFor(NoticeKind::ConditionChange, ['description' => 'Revisado ya']);
    $reviewed->update(['reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'Nota-interna']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id])->id]);

    $response = $this->get(route('portal.notices.show', noticeToken()));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('telework/portal-notices')
        ->where('name', 'Ana Ruiz')
        ->where('today', '2026-03-01')
        ->has('notices.data', 2)
        ->where('notices.data', fn ($notices): bool => collect($notices)->pluck('status', 'id')->all() === [$received->id => 'Recibido', $reviewed->id => 'Revisado']));
    expect($response->getContent())->not->toContain('Nota-interna');
});

it('shows the notices area only to a person with an open telework period', function (): void {
    $outsider = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $other = resolve(IssuePortalLink::class)->handle($outsider->id);

    $this->get(route('portal.notices.show', $other))->assertNotFound();
    $this->post(route('portal.notices.store', $other), noticeForm())->assertNotFound();
    $this->get(route('portal.notices.show', 'dead'))->assertNotFound();
    $this->post(route('portal.notices.store', 'dead'), noticeForm())->assertNotFound();

    $this->get(route('portal.show', $other))->assertInertia(fn (Assert $page) => $page->where('canNotify', false));
    $this->get(route('portal.show', noticeToken()))->assertInertia(fn (Assert $page) => $page->where('canNotify', true));
    $this->get(route('portal.show', 'dead'))->assertInertia(fn (Assert $page) => $page->where('canNotify', false));

    $this->teleworker->update(['status' => 'ended', 'ended_at' => now(), 'end_reason' => 'other']);
    $this->get(route('portal.notices.show', noticeToken()))->assertNotFound();
});

describe('a change of address (6.7)', function (): void {
    beforeEach(function (): void {
        ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $this->old = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
    });

    /** @return array<string, mixed> */
    function moveForm(array $overrides = []): array
    {
        return noticeForm(['kind' => 'address_change', 'description' => 'Me mudo.', 'street' => 'Calle Nueva', 'exterior_number' => '7', 'neighborhood' => 'Jardines', 'postal_code' => '76100', 'municipality' => 'Querétaro', 'state' => 'QUE', ...$overrides]);
    }

    function moveNotice(bool $temporary = false): WorkerNotice
    {
        $portal = resolve(OpenPortal::class)->handle(noticeToken());

        return resolve(SubmitNotice::class)->handle($portal, NoticeKind::AddressChange, CarbonImmutable::parse('2026-02-27'), CarbonImmutable::parse('2026-03-01'), 'Me mudo.', false, new AddressData('Calle Nueva', '7', null, 'Jardines', '76100', 'Querétaro', 'QUE'), $temporary, null);
    }

    function validateWorkplace(Workplace $workplace, ReviewDecision $decision = ReviewDecision::Valid): void
    {
        $application = ChecklistApplication::query()->where('workplace_id', $workplace->id)->firstOrFail();
        $application->update(['submitted_at' => now()]);
        resolve(ReviewWorkplace::class)->handle($workplace, $application, $decision, 'Notas', $decision === ReviewDecision::ValidWithPlan ? 'Plan' : null, $decision === ReviewDecision::ValidWithPlan ? CarbonImmutable::parse('2026-04-01') : null, test()->hr->id);
    }

    it('creates a new workplace with a Sección II application for an active teleworker', function (): void {
        Log::spy();

        $this->post(route('portal.notices.store', noticeToken()), moveForm(['temporary' => '1']))->assertSessionHasNoErrors();

        $notice = WorkerNotice::query()->sole();
        $new = Workplace::query()->findOrFail($notice->new_workplace_id);
        expect($new->street)->toBe('Calle Nueva')->and($new->teleworker_id)->toBe($this->teleworker->id)->and($new->retired_at)->toBeNull()
            ->and($notice->temporary)->toBeTrue()
            ->and(ChecklistApplication::query()->where('workplace_id', $new->id)->sole()->section)->toBe(Section::Two);
        Log::shouldHaveReceived('info')->with('telework.notice_submitted', ['notice_id' => $notice->id, 'teleworker_id' => $this->teleworker->id, 'kind' => 'address_change', 'traumatic_event' => false, 'temporary' => true])->once();
    });

    it('asks an aspirant for the Sección I of the new workplace', function (): void {
        $this->teleworker->update(['status' => 'aspirant']);

        $notice = moveNotice();

        expect(ChecklistApplication::query()->where('workplace_id', $notice->new_workplace_id)->sole()->section)->toBe(Section::One);
    });

    it('requires the new address', function (string $field): void {
        $this->post(route('portal.notices.store', noticeToken()), moveForm([$field => '']))->assertSessionHasErrors($field);

        expect(WorkerNotice::query()->count())->toBe(0)->and(Workplace::query()->count())->toBe(1);
    })->with(['street', 'exterior_number', 'neighborhood', 'postal_code', 'municipality', 'state']);

    it('does not ask for an address on the other kinds, nor for a temporary flag', function (): void {
        $this->post(route('portal.notices.store', noticeToken()), noticeForm(['temporary' => '1']))->assertSessionHasNoErrors();

        expect(WorkerNotice::query()->sole()->temporary)->toBeFalse()->and(Workplace::query()->count())->toBe(1);
    });

    it('refuses an address change without the address in the action, and leaves nothing behind', function (): void {
        $portal = resolve(OpenPortal::class)->handle(noticeToken());

        expect(fn () => resolve(SubmitNotice::class)->handle($portal, NoticeKind::AddressChange, CarbonImmutable::parse('2026-02-27'), CarbonImmutable::parse('2026-03-01'), 'Me mudo.', false, null, false, null))->toThrow(ValidationException::class);
        expect(WorkerNotice::query()->count())->toBe(0)->and(Workplace::query()->count())->toBe(1);
    });

    it('rolls the whole notice back when the new workplace cannot be requested', function (): void {
        DB::table('checklists')->where('work_center_id', $this->center->id)->update(['status' => 'draft', 'published_at' => null]);

        expect(fn () => moveNotice())->toThrow(ValidationException::class);
        expect(WorkerNotice::query()->count())->toBe(0)->and(Workplace::query()->count())->toBe(1);
    });

    it('keeps the old workplace on a temporary change, even when the new one is validated', function (): void {
        $notice = moveNotice(temporary: true);
        validateWorkplace(Workplace::query()->findOrFail($notice->new_workplace_id));

        expect($this->old->refresh()->retired_at)->toBeNull();
    });

    it('retires the old workplaces only when the new one is validated, with or without a plan', function (ReviewDecision $decision): void {
        $retired = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'retired_at' => '2026-01-01 00:00:00']);
        $other = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id])->id]);
        $notice = moveNotice();
        $new = Workplace::query()->findOrFail($notice->new_workplace_id);

        expect($this->old->refresh()->retired_at)->toBeNull();
        Log::spy();
        validateWorkplace($new, $decision);

        expect($this->old->refresh()->retired_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
            ->and($new->refresh()->retired_at)->toBeNull()
            ->and($retired->refresh()->retired_at?->toDateString())->toBe('2026-01-01')
            ->and($other->refresh()->retired_at)->toBeNull();
        Log::shouldHaveReceived('info')->with('telework.workplace_retired', ['workplace_id' => $this->old->id, 'replaced_by' => $new->id, 'notice_id' => $notice->id])->once();
    })->with([ReviewDecision::Valid, ReviewDecision::ValidWithPlan]);

    it('does not retire anything when the new workplace is not validated', function (): void {
        $notice = moveNotice();

        validateWorkplace(Workplace::query()->findOrFail($notice->new_workplace_id), ReviewDecision::NotValid);

        expect($this->old->refresh()->retired_at)->toBeNull();
    });

    it('does not retire anything when a workplace without a notice is validated', function (): void {
        $other = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
        ChecklistApplication::factory()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $other->id]);

        validateWorkplace($other);

        expect($this->old->refresh()->retired_at)->toBeNull();
    });
});
