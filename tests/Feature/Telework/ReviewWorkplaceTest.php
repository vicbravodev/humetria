<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\RecordVisit;
use App\Modules\Telework\Actions\ReviewWorkplace;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\ReviewMethod;
use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Http\Controllers\ReviewController;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Telework\Support\ReviewAccess;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(ReviewWorkplace::class, ReviewAccess::class, ReviewController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
    workIn($this->tenant);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $this->application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
});

/** @return array<string, mixed> */
function reviewForm(array $overrides = []): array
{
    return ['decision' => 'valid', 'notes' => '', ...$overrides];
}

function reviewUrl(?ChecklistApplication $application = null): string
{
    return route('tenant.telework.reviews.store', [test()->tenant, ($application ?? test()->application)->id]);
}

function decideReview(ReviewDecision $decision, string $notes = '', ?string $plan = null, ?string $due = null, ?ChecklistApplication $application = null, ?Workplace $workplace = null): WorkplaceReview
{
    return resolve(ReviewWorkplace::class)->handle($workplace ?? test()->workplace, $application ?? test()->application, $decision, $notes, $plan, $due === null ? null : CarbonImmutable::parse($due), test()->cora->id);
}

/** @return array<string, string> The messages of the validation error an attempt raises, by field. */
function reviewRejected(Closure $attempt): array
{
    try {
        $attempt();
    } catch (ValidationException $e) {
        return array_map(fn (array $messages): string => $messages[0], $e->errors());
    }

    return [];
}

it('records a decision on a submitted checklist and the workplace takes its status from it', function (ReviewDecision $decision, WorkplaceStatus $status): void {
    $this->travelTo('2026-03-10 18:00:00');

    $review = decideReview($decision, 'Se ve bien.', $decision === ReviewDecision::ValidWithPlan ? 'Cambiar la silla.' : null, $decision === ReviewDecision::ValidWithPlan ? '2026-04-10' : null);

    expect($review->method)->toBe(ReviewMethod::Checklist)
        ->and($review->decision)->toBe($decision)
        ->and($review->application_id)->toBe($this->application->id)
        ->and($review->workplace_id)->toBe($this->workplace->id)
        ->and($review->decided_by)->toBe($this->cora->id)
        ->and($review->decided_at->toIso8601String())->toBe('2026-03-10T18:00:00+00:00')
        ->and(resolve(WorkplaceStanding::class)->of($this->workplace))->toBe($status);
})->with([
    'valid' => [ReviewDecision::Valid, WorkplaceStatus::Valid],
    'valid with plan' => [ReviewDecision::ValidWithPlan, WorkplaceStatus::ValidWithPlan],
    'not valid' => [ReviewDecision::NotValid, WorkplaceStatus::NotValid],
]);

it('requires a plan and due date for valid_with_plan and notes for not_valid', function (): void {
    expect(reviewRejected(fn () => decideReview(ReviewDecision::ValidWithPlan)))->toHaveKeys(['action_plan', 'plan_due_on'])
        ->and(reviewRejected(fn () => decideReview(ReviewDecision::ValidWithPlan, plan: '   ', due: '2030-01-01')))->toHaveKey('action_plan')
        ->and(reviewRejected(fn () => decideReview(ReviewDecision::ValidWithPlan, plan: 'Cambiar la silla.')))->toHaveKey('plan_due_on')
        ->and(reviewRejected(fn () => decideReview(ReviewDecision::NotValid)))->toHaveKey('notes')
        ->and(reviewRejected(fn () => decideReview(ReviewDecision::NotValid, '  ')))->toHaveKey('notes')
        ->and(WorkplaceReview::query()->count())->toBe(0);
});

it('accepts a due date of today and rejects yesterday, counted in Mexico City', function (): void {
    // 03:00 UTC on the 11th is 21:00 on the 10th in Mexico City.
    $this->travelTo('2026-03-11 03:00:00');

    expect(reviewRejected(fn () => decideReview(ReviewDecision::ValidWithPlan, plan: 'Cambiar la silla.', due: '2026-03-09')))->toHaveKey('plan_due_on');

    $review = decideReview(ReviewDecision::ValidWithPlan, plan: 'Cambiar la silla.', due: '2026-03-10');

    expect($review->plan_due_on?->toDateString())->toBe('2026-03-10');
});

it('keeps the plan only for a validation with a plan', function (): void {
    $review = decideReview(ReviewDecision::Valid, 'Todo bien.', 'Un plan que no hace falta', '2030-01-01');

    expect($review->action_plan)->toBeNull()->and($review->plan_due_on)->toBeNull();
});

it('refuses a checklist that is not submitted, not of the workplace or already reviewed, and a retired workplace', function (): void {
    $waiting = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'section' => 2]);
    $otherWorkplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);
    $foreign = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $otherWorkplace->id]);

    expect(reviewRejected(fn () => decideReview(ReviewDecision::Valid, application: $waiting)))->toHaveKey('application')
        ->and(reviewRejected(fn () => decideReview(ReviewDecision::Valid, application: $foreign)))->toHaveKey('application');

    decideReview(ReviewDecision::Valid);

    expect(reviewRejected(fn () => decideReview(ReviewDecision::NotValid, 'Otra vez')))->toHaveKey('application')
        ->and(WorkplaceReview::query()->count())->toBe(1);

    $otherWorkplace->update(['retired_at' => now()]);

    expect(reviewRejected(fn () => decideReview(ReviewDecision::Valid, application: $foreign, workplace: $otherWorkplace)))->toHaveKey('workplace');
});

it('logs and audits the review with ids, the decision and the method, never the notes or the plan', function (): void {
    Log::spy();

    $review = decideReview(ReviewDecision::ValidWithPlan, 'Nota privada del lugar', 'Plan privado de cambios', '2030-01-01');
    $expected = [
        'review_id' => $review->id,
        'workplace_id' => $this->workplace->id,
        'application_id' => $this->application->id,
        'decision' => 'valid_with_plan',
        'method' => 'checklist',
    ];

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.workplace_reviewed'
        && $context === $expected
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'privad'))->once();

    $entry = AuditEntry::query()->where('event', 'telework.workplace_reviewed')->sole();
    expect($entry->subject_id)->toBe($review->id)
        ->and($entry->metadata)->toEqual($expected)
        ->and(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain('privad');
});

it('lets only an assigned committee member decide', function (): void {
    $this->actingAs($this->cora)->post(reviewUrl(), reviewForm(['notes' => 'Bien']))->assertSessionHasNoErrors()->assertRedirect();

    expect(WorkplaceReview::query()->sole()->decided_by)->toBe($this->cora->id);
});

it('lets an owner or HR decide once assigned to the center, and not before', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->post(reviewUrl(), reviewForm())->assertForbidden();
    expect(WorkplaceReview::query()->count())->toBe(0);

    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);
    $this->actingAs($user)->post(reviewUrl(), reviewForm())->assertSessionHasNoErrors()->assertRedirect();

    expect(WorkplaceReview::query()->count())->toBe(1);
})->with([TenantRole::Owner, TenantRole::Hr]);

it('hides the checklist from another center\'s committee and from a viewer', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'user_id' => $stranger->id]);

    $this->actingAs($stranger)->post(reviewUrl(), reviewForm())->assertNotFound();
    $this->actingAs($this->viewer)->post(reviewUrl(), reviewForm())->assertNotFound();
    $this->post(route('tenant.telework.reviews.store', [$this->tenant, 'not-a-uuid']), reviewForm())->assertNotFound();

    expect(WorkplaceReview::query()->count())->toBe(0);
});

it('does not let a member of another company reach the checklist', function (): void {
    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $spy = memberOf($rival, TenantRole::Committee);

    $this->actingAs($spy)->post(route('tenant.telework.reviews.store', ['rival', $this->application->id]), reviewForm())->assertNotFound();

    expect(WorkplaceReview::query()->count())->toBe(0);
});

it('does not trust the member whose reviewing role was taken away', function (): void {
    resolve(App\Modules\Tenancy\Support\CompanyRoles::class)->assign($this->cora, $this->tenant, TenantRole::Viewer);

    $this->actingAs($this->cora)->post(reviewUrl(), reviewForm())->assertNotFound();
});

it('answers a decision the rules refuse with its errors', function (array $form, string $field): void {
    $this->actingAs($this->cora)->post(reviewUrl(), $form)->assertSessionHasErrors($field);

    expect(WorkplaceReview::query()->count())->toBe(0);
})->with([
    'no decision' => [[], 'decision'],
    'unknown decision' => [['decision' => 'maybe'], 'decision'],
    'no notes for not valid' => [['decision' => 'not_valid'], 'notes'],
    'no plan' => [['decision' => 'valid_with_plan', 'plan_due_on' => '2030-01-01'], 'action_plan'],
    'no due date' => [['decision' => 'valid_with_plan', 'action_plan' => 'Cambiar la silla.'], 'plan_due_on'],
    'a due date that is not a date' => [['decision' => 'valid_with_plan', 'action_plan' => 'Plan', 'plan_due_on' => 'soon'], 'plan_due_on'],
]);

it('says the checklist was already reviewed', function (): void {
    $this->actingAs($this->cora);
    $this->post(reviewUrl(), reviewForm())->assertSessionHasNoErrors();

    $this->post(reviewUrl(), reviewForm())->assertSessionHasErrors('application');
});

it('records a remote check without a checklist', function (): void {
    $bare = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);

    $this->actingAs($this->cora)
        ->post(route('tenant.telework.workplaces.remote-check.store', [$this->tenant, $bare->id]), reviewForm(['decision' => 'not_valid', 'notes' => 'Por videollamada se vio el cable suelto.']))
        ->assertSessionHasNoErrors()->assertRedirect();

    $review = WorkplaceReview::query()->sole();
    expect($review->method)->toBe(ReviewMethod::RemoteCheck)->and($review->application_id)->toBeNull()->and($review->decision)->toBe(ReviewDecision::NotValid);
});

it('keeps the remote check of a workplace behind the same gate', function (): void {
    $url = route('tenant.telework.workplaces.remote-check.store', [$this->tenant, $this->workplace->id]);

    $this->actingAs($this->hr)->post($url, reviewForm())->assertForbidden();
    $this->actingAs($this->viewer)->post($url, reviewForm())->assertNotFound();
    $this->actingAs($this->cora)->post(route('tenant.telework.workplaces.remote-check.store', [$this->tenant, 'nope']), reviewForm())->assertNotFound();

    expect(WorkplaceReview::query()->count())->toBe(0);
});

it('does not validate by remote check a never-reviewed workplace with no sent list (one being answered or cancelled does not count)', function (ReviewDecision $decision): void {
    $bare = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);
    $remote = fn () => resolve(ReviewWorkplace::class)->handle($bare, null, $decision, 'Por videollamada.', 'Plan', CarbonImmutable::parse('2030-01-01'), $this->cora->id);

    expect(reviewRejected($remote))->toBe(['application' => 'Antes de validar, la persona debe contestar la lista de este lugar.']);

    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $bare->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $bare->id, 'cancelled_at' => now()]);

    expect(reviewRejected($remote))->toHaveKey('application')
        ->and(WorkplaceReview::query()->where('workplace_id', $bare->id)->count())->toBe(0);

    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $bare->id]);

    expect($remote()->method)->toBe(ReviewMethod::RemoteCheck);
})->with([ReviewDecision::Valid, ReviewDecision::ValidWithPlan]);

it('validates by remote check and by visit the new place of a moved active person, who was asked for a Sección II', function (): void {
    $moved = fn () => tap(WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]), function (Workplace $place): void {
        ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $place->id, 'section' => 2]);
    });

    $remote = resolve(ReviewWorkplace::class)->handle($moved(), null, ReviewDecision::Valid, '', null, null, $this->cora->id);
    $place = $moved();
    $authorization = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $place->id]);
    $visit = resolve(RecordVisit::class)->handle($place, $authorization, CarbonImmutable::now(config()->string('app.display_timezone')), ReviewDecision::Valid, '', null, null, $this->cora->id);

    expect($remote->method)->toBe(ReviewMethod::RemoteCheck)->and($remote->application_id)->not->toBeNull()
        ->and($visit->method)->toBe(ReviewMethod::Visit)->and($visit->application_id)->not->toBeNull();
});

it('does not validate again by remote check without a list sent after the previous review', function (): void {
    $this->application->update(['submitted_at' => '2026-03-09 18:00:00']);
    $this->travelTo('2026-03-10 18:00:00');
    decideReview(ReviewDecision::Valid);
    $this->travelTo('2027-02-20 18:00:00');
    $remote = fn () => resolve(ReviewWorkplace::class)->handle($this->workplace, null, ReviewDecision::Valid, '', null, null, $this->cora->id);

    expect(reviewRejected($remote))->toHaveKey('application');

    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'section' => 2]);

    expect($remote()->decision)->toBe(ReviewDecision::Valid);
});

it('still lets a remote check find a workplace not valid with no list', function (): void {
    $bare = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);

    $review = resolve(ReviewWorkplace::class)->handle($bare, null, ReviewDecision::NotValid, 'Se vio el cable suelto.', null, null, $this->cora->id);

    expect($review->decision)->toBe(ReviewDecision::NotValid)->and($review->method)->toBe(ReviewMethod::RemoteCheck);
});

it('refuses through the panel a remote-check validation with no list answered', function (): void {
    $bare = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);

    $this->actingAs($this->cora)->post(route('tenant.telework.workplaces.remote-check.store', [$this->tenant, $bare->id]), reviewForm())
        ->assertSessionHasErrors(['application' => 'Antes de validar, la persona debe contestar la lista de este lugar.']);

    expect(WorkplaceReview::query()->count())->toBe(0);
});

it('takes the sent list out of the inbox when a remote check decides the workplace, and it cannot be decided again', function (ReviewDecision $decision): void {
    $older = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'submitted_at' => now()->subDays(3)]);
    WorkplaceReview::factory()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'application_id' => $older->id, 'method' => 'checklist', 'decided_at' => now()->subDays(2)]);
    $inbox = route('tenant.telework.reviews.index', [$this->tenant, $this->center->id]);
    $this->actingAs($this->cora)->get($inbox)->assertInertia(fn ($page) => $page->where('applications.total', 1));

    $review = resolve(ReviewWorkplace::class)->handle($this->workplace, null, $decision, 'Por videollamada.', null, null, $this->cora->id);

    expect($review->method)->toBe(ReviewMethod::RemoteCheck)->and($review->application_id)->toBe($this->application->id);
    $this->get($inbox)->assertInertia(fn ($page) => $page->where('applications.total', 0));
    $this->post(reviewUrl(), reviewForm())->assertSessionHasErrors(['application' => 'Esta lista ya fue revisada.']);
    expect(WorkplaceReview::query()->where('workplace_id', $this->workplace->id)->count())->toBe(2);
})->with([ReviewDecision::Valid, ReviewDecision::NotValid]);

it('tells which unique rule a visit lost a race on: the list decided meanwhile, or the authorization used meanwhile', function (string $lost, array $error): void {
    $authorization = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $raced = false;
    // Another member commits between this visit's checks and its insert: the database's unique index is what refuses.
    WorkplaceReview::creating(function () use (&$raced, $lost, $authorization): void {
        if ($raced) {
            return;
        }

        $raced = true;
        WorkplaceReview::factory()->create($lost === 'list'
            ? ['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'application_id' => $this->application->id, 'method' => 'checklist']
            : ['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'application_id' => null, 'method' => 'visit', 'visit_authorization_id' => $authorization->id, 'visited_on' => now(config()->string('app.display_timezone'))->toDateString()]);
    });

    $visit = fn () => resolve(ReviewWorkplace::class)->handle($this->workplace, null, ReviewDecision::Valid, '', null, null, $this->cora->id, $authorization, CarbonImmutable::now(config()->string('app.display_timezone')));

    expect(reviewRejected($visit))->toBe($error);
})->with([
    'the list' => ['list', ['application' => 'Esta lista ya fue revisada.']],
    'the authorization' => ['authorization', ['visit_authorization_id' => 'Esta autorización ya se usó en otra visita.']],
]);
