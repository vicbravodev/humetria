<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\ReviewNotice;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Http\Controllers\PortalNoticeController;
use App\Modules\Telework\Jobs\SendNoticeAnswer;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Notifications\NoticeAnswered;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReviewNotice::class, PortalNoticeController::class, SendNoticeAnswer::class);

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
