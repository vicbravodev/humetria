<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\CloseNotice;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\RequestPlaceReview;
use App\Modules\Telework\Actions\ReviewNotice;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Http\Controllers\PortalNoticeController;
use App\Modules\Telework\Jobs\SendNoticeAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Notifications\NoticeAnswered;
use App\Modules\Telework\Support\NoticeEvidence;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReviewNotice::class, PortalNoticeController::class, SendNoticeAnswer::class, CloseNotice::class, RequestPlaceReview::class, NoticeEvidence::class);

// Rediseño v2, board V3-Aviso (decisión A17; docs/02, «Aviso de accidente»): RH or the Comisión records what was done about
// an accident (6.9), without clinical detail, and the person learns it in their portal.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Hugo Saiz', 'email' => 'hugo@example.com']);
    $this->teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    workIn($this->tenant);
    $this->notice = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
});

function answerAccident(array $form, ?WorkerNotice $notice = null): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.telework.notices.review', [test()->tenant, ($notice ?? test()->notice)->id]), $form);
}

describe('what was done', function (): void {
    it('records what was done with an optional note, and audits and logs ids only', function (): void {
        Queue::fake();
        Log::spy();
        $this->actingAs($this->hr);

        answerAccident(['actions' => ['medical_care', 'place_adjusted'], 'note' => '  Se le entregó una faja.  '])->assertSessionHasNoErrors()->assertRedirect();

        $this->notice->refresh();
        expect($this->notice->review_actions)->toBe(['medical_care', 'place_adjusted'])
            ->and($this->notice->review_note)->toBe('Se le entregó una faja.')
            ->and($this->notice->reviewed_by)->toBe($this->hr->id);
        expect(json_encode(AuditEntry::query()->where('event', 'telework.notice_reviewed')->sole()->metadata))->not->toContain('medical')->not->toContain('faja');
        Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'medical'));
    });

    it('needs nothing more than what was done', function (): void {
        Queue::fake();
        $this->actingAs($this->hr);

        answerAccident(['actions' => ['other']])->assertSessionHasNoErrors();

        expect($this->notice->refresh()->review_note)->toBeNull()->and($this->notice->review_actions)->toBe(['other']);
    });

    it('refuses an unknown action, a repeated one, and actions on a notice that is not an accident', function (): void {
        $this->actingAs($this->hr);
        $address = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'kind' => NoticeKind::ConditionChange]);

        answerAccident(['actions' => ['surgery']])->assertSessionHasErrors('actions.0');
        answerAccident(['actions' => ['leave', 'leave']])->assertSessionHasErrors('actions.1');
        answerAccident(['actions' => ['leave'], 'note' => 'x'], $address)->assertSessionHasErrors('actions');
        expect(fn () => resolve(ReviewNotice::class)->handle($address, 'Nota', $this->hr->id, [App\Modules\Telework\Enums\NoticeAction::Leave]))->toThrow(ValidationException::class);

        expect($this->notice->refresh()->reviewed_at)->toBeNull()->and($address->refresh()->reviewed_at)->toBeNull();
    });

    it('keeps the actions to accidents, and a review with something in it, in the database', function (): void {
        $address = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'kind' => NoticeKind::ConditionChange]);

        expect(fn () => $address->update(['reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'x', 'review_actions' => ['leave']]))->toThrow(QueryException::class);
        expect(fn () => $this->notice->update(['reviewed_by' => $this->hr->id, 'reviewed_at' => now()]))->toThrow(QueryException::class);
    });

    it('lets the Comisión of the center answer it, and nobody else', function (): void {
        Queue::fake();
        $cora = memberOf($this->tenant, TenantRole::Committee);
        CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
        answerAccident(['actions' => ['leave']])->assertNotFound();
        $this->actingAs($cora);
        answerAccident(['actions' => ['leave']])->assertSessionHasNoErrors();

        expect($this->notice->refresh()->reviewed_by)->toBe($cora->id);
    });
});

describe('the person learns it', function (): void {
    it('emails the person, in usted, that their notice was answered, with their portal link and nothing of the answer', function (): void {
        Notification::fake();
        $this->actingAs($this->hr);

        answerAccident(['actions' => ['medical_care'], 'note' => 'Se le entregó una faja.']);

        Notification::assertSentOnDemand(NoticeAnswered::class, function (NoticeAnswered $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $mail = $notification->toMail($notifiable);
            $text = json_encode($mail->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $notifiable->routes['mail'] === 'hugo@example.com'
                && $mail->subject === 'Su empresa respondió su aviso'
                && $mail->actionUrl === route('portal.notices.show', resolve(IssuePortalLink::class)->handle($this->employee->id))
                && ! str_contains($text, 'faja') && ! str_contains($text, 'médica');
        });
    });

    it('emails nobody for the review of another kind of notice, nor to a person without email', function (): void {
        Notification::fake();
        $this->actingAs($this->hr);
        $other = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'kind' => NoticeKind::ConditionChange]);
        answerAccident(['note' => 'Visto.'], $other)->assertSessionHasNoErrors();

        $this->employee->update(['email' => null]);
        answerAccident(['actions' => ['leave']])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    });

    it('shows the person in their portal what was done, but never an older note written for RH only', function (): void {
        Queue::fake();
        $this->actingAs($this->hr);
        answerAccident(['actions' => ['medical_care', 'leave'], 'note' => 'Se acordó no cargar más de 10 kg.']);
        $old = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'submitted_at' => '2026-01-10 10:00:00', 'reviewed_by' => $this->hr->id, 'reviewed_at' => '2026-01-11 10:00:00', 'review_note' => 'Nota-interna']);

        $response = $this->get(route('portal.notices.show', resolve(IssuePortalLink::class)->handle($this->employee->id)));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('notices.data.0.answer', ['actions' => ['Se canalizó a atención médica', 'Se le dio un permiso para recuperarse'], 'note' => 'Se acordó no cargar más de 10 kg.', 'answeredOn' => '2026-03-01'])
            ->where('notices.data.1.id', $old->id)
            ->where('notices.data.1.answer', null));
        expect($response->getContent())->not->toContain('Nota-interna');
    });
});

describe('closing it', function (): void {
    beforeEach(function (): void {
        Queue::fake();
        Storage::fake('telework_files');
        config()->set('filesystems.telework_files', 'telework_files');
        $this->actingAs($this->hr);
    });

    function closeAccident(?UploadedFile $evidence, ?WorkerNotice $notice = null): Illuminate\Testing\TestResponse
    {
        return test()->post(route('tenant.telework.notices.close', [test()->tenant, ($notice ?? test()->notice)->id]), $evidence === null ? [] : ['evidence' => $evidence]);
    }

    it('closes an answered accident with its evidence, kept private, and audits and logs ids only', function (): void {
        Log::spy();
        answerAccident(['actions' => ['place_adjusted']]);
        $this->travelTo('2026-03-04 12:00:00');

        closeAccident(UploadedFile::fake()->create('acuerdo.pdf', 20, 'application/pdf'))->assertSessionHasNoErrors()->assertRedirect();

        $this->notice->refresh();
        expect($this->notice->closed_at?->toIso8601String())->toBe('2026-03-04T12:00:00+00:00')
            ->and($this->notice->closed_by)->toBe($this->hr->id)
            ->and($this->notice->evidence_path)->toEndWith('.pdf');
        Storage::disk('telework_files')->assertExists((string) $this->notice->evidence_path);
        expect(AuditEntry::query()->where('event', 'telework.notice_closed')->sole()->subject_id)->toBe($this->notice->id);
        Log::shouldHaveReceived('info')->with('telework.notice_closed', ['notice_id' => $this->notice->id, 'teleworker_id' => $this->teleworker->id])->once();

        $this->get(route('tenant.telework.notices.evidence', [$this->tenant, $this->notice->id]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    });

    it('takes a photo as evidence, without what the camera wrote in it', function (): void {
        answerAccident(['actions' => ['place_adjusted']]);

        closeAccident(UploadedFile::fake()->image('cambio.jpg', 40, 40))->assertSessionHasNoErrors();

        expect($this->notice->refresh()->evidence_path)->toEndWith('.jpg');
    });

    it('needs the evidence, the answer first, and closes once', function (): void {
        closeAccident(UploadedFile::fake()->create('acuerdo.pdf', 20, 'application/pdf'))->assertSessionHasErrors('notice');
        answerAccident(['actions' => ['leave']]);
        closeAccident(null)->assertSessionHasErrors('evidence');
        closeAccident(UploadedFile::fake()->create('nota.txt', 2, 'text/plain'))->assertSessionHasErrors('evidence');
        closeAccident(UploadedFile::fake()->create('acuerdo.pdf', 20, 'application/pdf'))->assertSessionHasNoErrors();
        $first = $this->notice->refresh()->evidence_path;

        closeAccident(UploadedFile::fake()->create('otro.pdf', 20, 'application/pdf'))->assertSessionHasErrors('notice');

        expect($this->notice->refresh()->evidence_path)->toBe($first)
            ->and(Storage::disk('telework_files')->allFiles())->toHaveCount(1);
    });

    it('closes only an accident, in the action too', function (): void {
        $other = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'kind' => NoticeKind::ConditionChange, 'reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'Visto']);

        closeAccident(UploadedFile::fake()->create('acuerdo.pdf', 20, 'application/pdf'), $other)->assertSessionHasErrors('notice');

        expect($other->refresh()->closed_at)->toBeNull();
    });

    it('keeps the evidence and the closing from the Comisión of another center, a viewer and another company', function (): void {
        answerAccident(['actions' => ['leave']]);
        closeAccident(UploadedFile::fake()->create('acuerdo.pdf', 20, 'application/pdf'));
        $elsewhere = memberOf($this->tenant, TenantRole::Committee);
        CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'user_id' => $elsewhere->id]);

        foreach ([$elsewhere, memberOf($this->tenant, TenantRole::Viewer), memberOf(Tenant::factory()->create(), TenantRole::Hr)] as $outsider) {
            $this->actingAs($outsider)->get(route('tenant.telework.notices.evidence', [$this->tenant, $this->notice->id]))->assertNotFound();
        }

        $this->actingAs($this->hr)->get(route('tenant.telework.notices.evidence', [$this->tenant, WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id])->id]))->assertNotFound();
    });
});

describe('asking the Comisión to review the place', function (): void {
    beforeEach(function (): void {
        Queue::fake();
        $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
        $this->actingAs($this->hr);
    });

    function askPlaceReview(?WorkerNotice $notice = null): Illuminate\Testing\TestResponse
    {
        return test()->post(route('tenant.telework.notices.place-review', [test()->tenant, ($notice ?? test()->notice)->id]));
    }

    it('asks the person to answer the list of their place again, for the Comisión to review it, once', function (): void {
        ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

        askPlaceReview()->assertSessionHasNoErrors()->assertRedirect();
        $this->travelTo('2026-03-02 12:00:00');
        askPlaceReview()->assertSessionHasNoErrors();

        $application = ChecklistApplication::query()->sole();
        expect($application->workplace_id)->toBe($this->workplace->id)
            ->and($application->section->value)->toBe(2)
            ->and($this->notice->refresh()->place_review_requested_at?->toDateString())->toBe('2026-03-01');
        Queue::assertPushed(App\Modules\Telework\Jobs\SendPortalReminder::class);
    });

    it('cannot ask before the Comisión publishes its list, nor for a person without a place in use', function (): void {
        askPlaceReview()->assertSessionHasErrors('work_center');
        ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $this->workplace->update(['retired_at' => now()]);

        askPlaceReview()->assertSessionHasErrors('notice');

        expect(ChecklistApplication::query()->count())->toBe(0)->and($this->notice->refresh()->place_review_requested_at)->toBeNull();
    });

    it('asks only about an accident', function (): void {
        ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $other = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'kind' => NoticeKind::ConditionChange]);

        askPlaceReview($other)->assertSessionHasErrors('notice');
        expect(fn () => resolve(RequestPlaceReview::class)->handle($other))->toThrow(ValidationException::class);
    });
});
