<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\ReviewMethod;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The Comisión decides about a workplace (criterio C-12): Validated, Validated with an action plan, or Not validated.
 * With a submitted checklist it is a `checklist` review (the checklist is reviewed once); with the authorization of an
 * in-person visit (see `RecordVisit`, which checks it), a `visit`; with neither, a `remote_check`, which also closes the
 * remote checks the Comisión had asked for. A visit or a remote check validates only a workplace whose person answered its
 * list (`requireAnsweredList`).
 * "With plan" needs the plan and a due date that is not in the past; "Not validated" needs notes. Who may decide is the
 * controller's business (`ReviewAccess`): this only judges what is decided.
 *
 * Notes and plan are personal data: never logged or audited.
 */
final readonly class ReviewWorkplace
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(Workplace $workplace, ?ChecklistApplication $application, ReviewDecision $decision, string $notes, ?string $actionPlan, ?CarbonImmutable $planDueOn, int $userId, ?VisitAuthorization $authorization = null, ?CarbonImmutable $visitedOn = null): WorkplaceReview
    {
        $notes = trim($notes);
        $plan = trim($actionPlan ?? '');
        $this->validate($workplace, $decision, $notes, $plan, $planDueOn);
        $withPlan = $decision === ReviewDecision::ValidWithPlan;

        try {
            return DB::transaction(function () use ($workplace, $application, $decision, $notes, $plan, $planDueOn, $userId, $withPlan, $authorization, $visitedOn): WorkplaceReview {
                $this->checkApplication($workplace, $application);
                $method = match (true) {
                    $application !== null => ReviewMethod::Checklist,
                    $authorization !== null => ReviewMethod::Visit,
                    default => ReviewMethod::RemoteCheck,
                };

                if ($method !== ReviewMethod::Checklist && $decision->validates()) {
                    $this->requireAnsweredList($workplace);
                }

                $review = WorkplaceReview::query()->create([
                    'tenant_id' => $workplace->tenant_id,
                    'workplace_id' => $workplace->id,
                    'application_id' => $application?->id,
                    'method' => $method,
                    'decision' => $decision,
                    'notes' => $notes,
                    'action_plan' => $withPlan ? $plan : null,
                    'plan_due_on' => $withPlan ? $planDueOn?->toDateString() : null,
                    'decided_by' => $userId,
                    'decided_at' => now(),
                    'visited_on' => $visitedOn?->toDateString(),
                    'visit_authorization_id' => $authorization?->id,
                ]);

                $record = [
                    'review_id' => $review->id,
                    'workplace_id' => $workplace->id,
                    'application_id' => $application?->id,
                    'decision' => $decision->value,
                    'method' => $review->method->value,
                ];
                $this->audit->handle('telework.workplace_reviewed', $review, $record);
                Log::info('telework.workplace_reviewed', $record);

                if ($method === ReviewMethod::RemoteCheck) {
                    $this->closeRemoteChecks($workplace);
                }

                if ($decision->validates()) {
                    $this->retireReplaced($workplace);
                }

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages($authorization === null
                ? ['application' => 'Esta lista ya fue revisada.']
                : ['visit_authorization_id' => 'Esta autorización ya se usó en otra visita.']);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validate(Workplace $workplace, ReviewDecision $decision, string $notes, string $plan, ?CarbonImmutable $planDueOn): void
    {
        $errors = [];

        if ($workplace->retired_at !== null) {
            $errors['workplace'] = 'Este lugar ya está retirado.';
        }

        if ($decision === ReviewDecision::NotValid && $notes === '') {
            $errors['notes'] = 'Escribe las notas: explican por qué el lugar no se valida.';
        }

        if ($decision === ReviewDecision::ValidWithPlan) {
            if ($plan === '') {
                $errors['action_plan'] = 'Escribe el plan de acción.';
            }

            if ($planDueOn === null) {
                $errors['plan_due_on'] = 'Elige la fecha compromiso del plan.';
            } elseif ($planDueOn->toDateString() < CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString()) {
                $errors['plan_due_on'] = 'La fecha compromiso no puede estar en el pasado.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The checklist must be a submitted one of this workplace that nobody reviewed yet. Locked, so two members deciding
     * the same list at once cannot both pass (the unique index is the backstop).
     *
     * @throws ValidationException
     */
    private function checkApplication(Workplace $workplace, ?ChecklistApplication $application): void
    {
        if ($application === null) {
            return;
        }

        $fresh = ChecklistApplication::query()->where('workplace_id', $workplace->id)->whereNotNull('submitted_at')->lockForUpdate()->find($application->id);

        if ($fresh === null) {
            throw ValidationException::withMessages(['application' => 'Esta lista aún no se envía o no es de este lugar.']);
        }

        if (WorkplaceReview::query()->where('application_id', $fresh->id)->exists()) {
            throw ValidationException::withMessages(['application' => 'Esta lista ya fue revisada.']);
        }
    }

    /**
     * A visit or a remote check does not replace the list (criterios C-12 and C-14): the person answers it anyway. To
     * validate without reviewing a list, the workplace needs a submitted, not cancelled one: a Sección I when it was never
     * reviewed, and any list sent after the previous review otherwise. Finding it not valid needs none.
     *
     * @throws ValidationException
     */
    private function requireAnsweredList(Workplace $workplace): void
    {
        $previous = WorkplaceReview::query()->where('workplace_id', $workplace->id)->orderByDesc('decided_at')->orderByDesc('id')->first();
        $answered = ChecklistApplication::query()->where('workplace_id', $workplace->id)->whereNotNull('submitted_at')->whereNull('cancelled_at')
            ->when($previous === null, fn ($lists) => $lists->where('section', Section::One))
            ->when($previous !== null, fn ($lists) => $lists->where('submitted_at', '>', $previous?->decided_at))
            ->exists();

        if (! $answered) {
            throw ValidationException::withMessages(['application' => 'Antes de validar, la persona debe contestar la lista de este lugar.']);
        }
    }

    /**
     * The new workplace of a permanent change of address (criterio C-14), once validated, replaces the places the person
     * already had when they wrote the notice: they are retired, kept as history. It happens once per notice. A temporary
     * change retires nothing, nor does a workplace no notice named, nor does one added after the notice.
     */
    private function retireReplaced(Workplace $workplace): void
    {
        $notice = WorkerNotice::query()->where('new_workplace_id', $workplace->id)->where('temporary', false)->whereNull('replaced_at')->lockForUpdate()->first();

        if ($notice === null) {
            return;
        }

        $old = Workplace::query()->where('teleworker_id', $workplace->teleworker_id)->whereKeyNot($workplace->id)->whereNull('retired_at')
            ->where('created_at', '<', $notice->submitted_at)->lockForUpdate()->get();

        foreach ($old as $place) {
            $place->update(['retired_at' => now()]);
            Log::info('telework.workplace_retired', ['workplace_id' => $place->id, 'replaced_by' => $workplace->id, 'notice_id' => $notice->id]);
        }

        $notice->update(['replaced_at' => now()]);
    }

    /** A remote check the Comisión had asked for is done by recording it. */
    private function closeRemoteChecks(Workplace $workplace): void
    {
        $open = EvidenceRequest::query()->where('workplace_id', $workplace->id)->where('kind', EvidenceKind::RemoteCheck)->whereNull('fulfilled_at')->get();

        foreach ($open as $request) {
            $request->update(['fulfilled_at' => now()]);
            Log::info('telework.evidence_fulfilled', ['evidence_request_id' => $request->id, 'workplace_id' => $workplace->id, 'kind' => 'remote_check']);
        }
    }
}
