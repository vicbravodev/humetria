<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\PhotoController;
use App\Modules\Telework\Http\Controllers\ReviewController;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EvidencePhoto;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ReviewDetail;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReviewDetail::class, ReviewController::class, PhotoController::class);

beforeEach(function (): void {
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $this->center = WorkCenter::query()->findOrFail($this->employee->work_center_id);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
    $this->teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
    $this->checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
});

function inboxUrl(?WorkCenter $center = null): string
{
    return route('tenant.telework.reviews.index', [test()->tenant, ($center ?? test()->center)->id]);
}

/** A submitted checklist of the workplace, the oldest by `$daysAgo`. */
function submittedApplication(int $daysAgo = 1, ?Workplace $workplace = null, int $section = 1): ChecklistApplication
{
    return ChecklistApplicationFactory::new()->create([
        'tenant_id' => test()->tenant->id,
        'workplace_id' => ($workplace ?? test()->workplace)->id,
        'checklist_id' => test()->checklist->id,
        'section' => $section,
        'requested_at' => now()->subDays($daysAgo + 1),
        'submitted_at' => now()->subDays($daysAgo),
    ]);
}

it('orders the inbox oldest first and paginates it', function (): void {
    $this->travelTo('2026-05-11 00:30:00'); // UTC; still 2026-05-10 in Mexico City, the day the submission is shown on
    $newest = submittedApplication(1);
    $oldest = submittedApplication(9, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'street' => 'Calle Juárez']));

    $this->actingAs($this->cora)->get(inboxUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/reviews')
        ->where('workCenter.name', $this->center->name)
        ->has('applications', fn (Assert $list) => $list->hasAll(['data', 'current_page', 'last_page', 'per_page', 'total', 'prev_page_url', 'next_page_url', 'links'])
            ->where('total', 2)
            ->where('data.0.id', $oldest->id)
            ->where('data.0.person', 'Ana Ruiz')
            ->where('data.0.jobTitle', 'Analista')
            ->where('data.0.workplace', 'Calle Juárez 45, Centro, Querétaro, Querétaro')
            ->where('data.0.section', 1)
            ->where('data.0.submittedOn', '2026-05-01')
            ->where('data.1.id', $newest->id)
            ->etc()));
});

it('pages the inbox at fifteen', function (): void {
    foreach (range(1, 16) as $n) {
        submittedApplication($n + 1, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]), section: 1);
    }

    $this->actingAs($this->cora)->get(inboxUrl())->assertInertia(fn (Assert $page) => $page->where('applications.total', 16)->where('applications.per_page', 15)->has('applications.data', 15));
    $this->actingAs($this->cora)->get(inboxUrl().'?page=2')->assertInertia(fn (Assert $page) => $page->has('applications.data', 1));
});

it('leaves out what is not waiting for this center', function (): void {
    // Not yet sent, already reviewed, a retired workplace, and another center's person.
    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'checklist_id' => $this->checklist->id]);
    $reviewed = submittedApplication(3, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]));
    WorkplaceReviewFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $reviewed->workplace_id, 'application_id' => $reviewed->id, 'method' => 'checklist']);
    submittedApplication(4, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'retired_at' => now()]));
    $stranger = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id]);
    submittedApplication(5, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $stranger->id]));
    $waiting = submittedApplication(2);

    $this->actingAs($this->cora)->get(inboxUrl())->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->where('applications.data.0.id', $waiting->id));
});

it('leaves out the lists of a person whose period ended and the cancelled ones, in the inbox and in the center counts', function (): void {
    $gone = TeleworkerFactory::new()->ended()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
    submittedApplication(3, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $gone->id]));
    submittedApplication(4)->update(['cancelled_at' => now()]);
    $waiting = submittedApplication(2, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]));

    $this->actingAs($this->cora)->get(inboxUrl())->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->where('applications.data.0.id', $waiting->id));
    $this->actingAs($this->owner)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('centers.data.0.pendingReviews', 1));
});

it('asserts the query count of the inbox', function (): void {
    foreach (range(1, 3) as $n) {
        submittedApplication($n, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]));
    }
    $this->actingAs($this->cora)->get(inboxUrl())->assertOk();

    $this->expectsDatabaseQueryCount(19);
    $this->get(inboxUrl())->assertOk();
});

it('queries the inbox the same number of times for one row as for a full page', function (): void {
    $queries = function (): int {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        test()->actingAs(test()->cora)->get(inboxUrl())->assertOk();

        return $count;
    };
    submittedApplication(1);
    $queries();
    $one = $queries();

    foreach (range(2, 12) as $n) {
        submittedApplication($n, WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id])->id]));
    }

    $this->actingAs($this->cora)->get(inboxUrl())->assertInertia(fn (Assert $page) => $page->where('applications.total', 12));
    expect($queries())->toBe($one);
});

it('shows the inbox only to the committee assigned to the center', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'user_id' => $stranger->id]);

    $this->actingAs($this->cora)->get(inboxUrl())->assertOk();
    $this->actingAs($this->hr)->get(inboxUrl())->assertForbidden();
    $this->actingAs($this->owner)->get(inboxUrl())->assertForbidden();
    $this->actingAs($stranger)->get(inboxUrl())->assertNotFound();
    $this->actingAs($this->viewer)->get(inboxUrl())->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.index', [$this->tenant, 'nope']))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.index', [$this->tenant, Str::uuid()->toString()]))->assertNotFound();

    $this->actingAs($this->hr);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->hr->id]);
    $this->get(inboxUrl())->assertOk();
});

it('does not show another company\'s center', function (): void {
    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $spy = memberOf($rival, TenantRole::Committee);

    $this->actingAs($spy)->get(route('tenant.telework.reviews.index', ['rival', $this->center->id]))->assertNotFound();
});

/** An application with an unfavorable answer (with note and photo), a favorable one, an informative one and a general photo. */
function answeredApplication(): array
{
    $t = test();
    $risk = addQuestion($t->checklist, 1, 'yes');
    $fine = addQuestion($t->checklist, 2, 'yes');
    $info = addQuestion($t->checklist, 3, 'informative');
    $inverse = addQuestion($t->checklist, 4, 'no');
    $health = addQuestion($t->checklist, 5, 'yes', sensitive: true);
    $application = submittedApplication();
    foreach ([[$fine, 'yes', null], [$risk, 'no', 'La luz es muy poca'], [$info, 'yes', null], [$inverse, 'yes', 'Trabaja con la puerta abierta'], [$health, null, null]] as [$question, $answer, $note]) {
        ChecklistAnswer::query()->create(['tenant_id' => $t->tenant->id, 'application_id' => $application->id, 'question_id' => $question->id, 'answer' => $answer, 'risk_note' => $note]);
    }
    $photo = ChecklistPhoto::query()->create(['tenant_id' => $t->tenant->id, 'application_id' => $application->id, 'question_id' => $risk->id, 'path' => 'checklists/x/risk.jpg', 'uploaded_at' => now()]);
    $general = ChecklistPhoto::query()->create(['tenant_id' => $t->tenant->id, 'application_id' => $application->id, 'question_id' => null, 'path' => 'checklists/x/general.jpg', 'uploaded_at' => now()]);
    Storage::disk('telework_files')->put('checklists/x/risk.jpg', 'RISK-BYTES');
    Storage::disk('telework_files')->put('checklists/x/general.jpg', 'GENERAL-BYTES');

    return [$application, compact('risk', 'fine', 'info', 'inverse', 'health'), $photo, $general];
}

it('shows unfavorable answers first with notes and photos, served through an authorized route', function (): void {
    [$application, $q, $photo, $general] = answeredApplication();
    $application->update(['special_needs' => 'Escritorio alto', 'consent_id' => null]);

    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.show', [$this->tenant, $application->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/review')
        ->where('workCenter.id', $this->center->id)
        ->where('review.person', ['name' => 'Ana Ruiz', 'jobTitle' => 'Analista'])
        ->where('review.workplace.address', 'Calle Hidalgo 45, Centro, Querétaro, Querétaro')
        ->where('review.workplace.status', 'pending')
        ->where('review.workplace.expiresOn', null)
        ->where('review.application.section', 1)
        ->where('review.application.specialNeeds', 'Escritorio alto')
        ->where('review.answers.unfavorable', fn ($list) => collect($list)->pluck('id')->all() === [$q['risk']->id, $q['inverse']->id])
        ->where('review.answers.unfavorable.0.note', 'La luz es muy poca')
        ->where('review.answers.unfavorable.0.answer', 'no')
        ->where('review.answers.unfavorable.0.photos', [['id' => $photo->id]])
        ->where('review.answers.other', fn ($list) => collect($list)->pluck('id')->all() === [$q['fine']->id, $q['health']->id])
        ->where('review.answers.other.1.answer', null)
        ->where('review.answers.informative.0.id', $q['info']->id)
        ->where('review.answers.generalPhotos', [['id' => $general->id]])
        ->where('review.canDecide', true)
        ->where('review.current', null)
        ->where('review.previous', null));

    $this->get(route('tenant.telework.photos.show', [$this->tenant, $photo->id]))->assertOk()->assertStreamedContent('RISK-BYTES');
});

it('shows the previous review of the workplace, the evidence asked for and the visit authorizations', function (): void {
    $application = submittedApplication();
    WorkplaceReviewFactory::new()->withPlan()->decidedAt('2025-01-15 18:00:00')->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'decided_by' => $this->cora->id]);
    $request = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'fulfilled_at' => now()]);
    $evidence = EvidencePhoto::query()->create(['tenant_id' => $this->tenant->id, 'evidence_request_id' => $request->id, 'path' => 'evidence/x/p.jpg', 'uploaded_at' => now()]);
    $waiting = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'requested_at' => now()->subDay()]);
    $given = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);

    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.show', [$this->tenant, $application->id]))->assertInertia(fn (Assert $page) => $page
        ->where('review.previous.decision', 'valid_with_plan')
        ->where('review.previous.decisionLabel', 'Validado con plan de acción')
        ->where('review.previous.methodLabel', 'Comprobación a distancia')
        ->where('review.previous.decidedOn', '2025-01-15')
        ->where('review.previous.actionPlan', 'Cambiar la silla.')
        ->where('review.workplace.status', 'pending')
        ->where('review.evidence.0.id', $request->id)
        ->where('review.evidence.0.fulfilled', true)
        ->where('review.evidence.0.photos', [['id' => $evidence->id]])
        ->where('review.authorizations.0.id', $given->id)
        ->where('review.authorizations.0.viaPortal', true)
        ->where('review.authorizations.0.authorized', true)
        ->where('review.authorizations.0.used', false)
        ->where('review.authorizations.1.id', $waiting->id)
        ->where('review.authorizations.1.authorized', false));
});

it('shows the decision of a reviewed checklist and no longer offers to decide', function (): void {
    $application = submittedApplication();
    $this->actingAs($this->cora)->post(route('tenant.telework.reviews.store', [$this->tenant, $application->id]), ['decision' => 'valid', 'notes' => 'Todo en orden.'])->assertRedirect(inboxUrl());

    $this->get(route('tenant.telework.reviews.show', [$this->tenant, $application->id]))->assertInertia(fn (Assert $page) => $page
        ->where('review.canDecide', false)
        ->where('review.current.decision', 'valid')
        ->where('review.current.notes', 'Todo en orden.')
        ->where('review.workplace.status', 'valid')
        ->where('review.workplace.expiresOn', now()->setTimezone(config()->string('app.display_timezone'))->addMonthsNoOverflow(12)->toDateString())
        ->where('review.previous', null));

    $this->get(inboxUrl())->assertInertia(fn (Assert $page) => $page->where('applications.total', 0));
});

it('keeps the checklist detail behind the same gate', function (): void {
    $application = submittedApplication();
    $waiting = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'checklist_id' => $this->checklist->id, 'section' => 2]);
    $url = route('tenant.telework.reviews.show', [$this->tenant, $application->id]);
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $stranger->id]);

    $this->actingAs($this->cora)->get($url)->assertOk();
    $this->actingAs($this->hr)->get($url)->assertForbidden();
    $this->actingAs($stranger)->get($url)->assertNotFound();
    $this->actingAs($this->viewer)->get($url)->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.show', [$this->tenant, $waiting->id]))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.reviews.show', [$this->tenant, 'nope']))->assertNotFound();
});

it('serves the photos to the assigned committee and RH, not to a viewer, nor across companies', function (): void {
    [, , $photo] = answeredApplication();
    $url = route('tenant.telework.photos.show', [$this->tenant, $photo->id]);
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $stranger->id]);

    foreach ([$this->cora, $this->hr, $this->owner] as $user) {
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    $this->actingAs($this->viewer)->get($url)->assertForbidden();
    $this->actingAs($stranger)->get($url)->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.photos.show', [$this->tenant, 'nope']))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.photos.show', [$this->tenant, Str::uuid()->toString()]))->assertNotFound();

    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $this->actingAs(memberOf($rival, TenantRole::Owner))->get(route('tenant.telework.photos.show', ['rival', $photo->id]))->assertNotFound();
});

it('serves the photos the person sent for a request and the signed paper authorization', function (): void {
    $request = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'fulfilled_at' => now()]);
    $photo = EvidencePhoto::query()->create(['tenant_id' => $this->tenant->id, 'evidence_request_id' => $request->id, 'path' => 'evidence/x/p.jpg', 'uploaded_at' => now()]);
    $paper = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'document_path' => 'visits/x/paper.pdf']);
    $bare = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Storage::disk('telework_files')->put('evidence/x/p.jpg', 'EVIDENCE-BYTES');
    Storage::disk('telework_files')->put('visits/x/paper.pdf', 'PAPER-BYTES');

    $this->actingAs($this->cora)->get(route('tenant.telework.evidence-photos.show', [$this->tenant, $photo->id]))->assertOk()->assertStreamedContent('EVIDENCE-BYTES');
    $this->actingAs($this->hr)->get(route('tenant.telework.visit-authorizations.document.show', [$this->tenant, $paper->id]))->assertOk()->assertStreamedContent('PAPER-BYTES');
    $this->actingAs($this->viewer)->get(route('tenant.telework.evidence-photos.show', [$this->tenant, $photo->id]))->assertForbidden();
    $this->actingAs($this->viewer)->get(route('tenant.telework.visit-authorizations.document.show', [$this->tenant, $paper->id]))->assertForbidden();
    $this->actingAs($this->cora)->get(route('tenant.telework.visit-authorizations.document.show', [$this->tenant, $bare->id]))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.evidence-photos.show', [$this->tenant, 'nope']))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.telework.visit-authorizations.document.show', [$this->tenant, 'nope']))->assertNotFound();
});

it('offers the inbox from the committee page to its assigned members only', function (): void {
    SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $url = route('tenant.telework.committee.show', [$this->tenant, $this->center->id]);

    $this->actingAs($this->cora)->get($url)->assertInertia(fn (Assert $page) => $page->where('canReview', true));
    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('canReview', false));
});
