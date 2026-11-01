<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\EvidencePhoto;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Carbon\CarbonInterface;
use LogicException;

/**
 * What the Comisión sees when it opens a submitted checklist: the person and the place, the answers (the unfavorable
 * ones first, with their notes and photos; the informative apart), the place's previous decision, the evidence asked for
 * and the visit authorizations. Photos travel as ids: the page links them to the authorized route (`PhotoController`), never as files.
 */
final readonly class ReviewDetail
{
    public function __construct(private Roster $roster, private WorkplaceAddress $address, private WorkplaceStanding $standing) {}

    /**
     * @return array<string, mixed>
     */
    public function for(ChecklistApplication $application): array
    {
        $workplace = Workplace::query()->findOrFail($application->workplace_id);
        $period = Teleworker::query()->findOrFail($workplace->teleworker_id);
        $person = $this->roster->find($period->employee_id) ?? throw new LogicException("Teleworker [{$period->id}] has no employee.");
        $current = WorkplaceReview::query()->where('application_id', $application->id)->first();
        $previous = WorkplaceReview::query()->where('workplace_id', $workplace->id)
            ->where(fn ($reviews) => $reviews->whereNull('application_id')->orWhere('application_id', '!=', $application->id))
            ->orderByDesc('decided_at')->orderByDesc('id')->first();
        $status = $this->standing->of($workplace);
        $expires = $this->standing->expiresOn($workplace);

        return [
            'application' => [
                'id' => $application->id,
                'section' => $application->section->value,
                'submittedOn' => $application->submitted_at === null ? null : $this->day($application->submitted_at),
                'consentAccepted' => $application->consent_accepted_at !== null,
                'specialNeeds' => $application->special_needs,
            ],
            'person' => ['name' => $person->name, 'jobTitle' => $person->jobTitle],
            'workplace' => [
                'id' => $workplace->id,
                'address' => $this->address->line($workplace),
                'status' => $status->value,
                'statusLabel' => $status->label(),
                'expiresOn' => $expires?->toDateString(),
            ],
            'answers' => $this->answers($application),
            'current' => $current === null ? null : $this->decision($current),
            'previous' => $previous === null ? null : $this->decision($previous),
            'canDecide' => $current === null && $workplace->retired_at === null,
            'evidence' => $this->evidence($workplace),
            'authorizations' => $this->authorizations($workplace),
        ];
    }

    /**
     * @return array{unfavorable: list<array<string, mixed>>, other: list<array<string, mixed>>, informative: list<array<string, mixed>>, generalPhotos: list<array{id: string}>}
     */
    private function answers(ChecklistApplication $application): array
    {
        $questions = ChecklistQuestion::query()->where('checklist_id', $application->checklist_id)->where('section', $application->section->value)->orderBy('position')->get();
        $answers = ChecklistAnswer::query()->where('application_id', $application->id)->get()->keyBy('question_id');
        $photos = $this->photos($application);
        $groups = ['unfavorable' => [], 'other' => [], 'informative' => []];

        foreach ($questions as $question) {
            $given = $answers->get($question->id);
            $answer = $given?->answer;
            $group = match (true) {
                $question->favorable === Favorable::Informative => 'informative',
                $answer !== null && AnswerRules::unfavorable($question->favorable, $answer) => 'unfavorable',
                default => 'other',
            };
            $groups[$group][] = [
                'id' => $question->id,
                'code' => $question->code,
                'heading' => $question->heading,
                'text' => $question->text,
                'answer' => $answer,
                'note' => $given?->risk_note,
                'photos' => $photos[$question->id] ?? [],
            ];
        }

        return [...$groups, 'generalPhotos' => $photos[AnswerRules::GENERAL] ?? []];
    }

    /**
     * The photo ids of an application by question id (or `general`), in the order they were sent.
     *
     * @return array<string, list<array{id: string}>>
     */
    private function photos(ChecklistApplication $application): array
    {
        $photos = [];

        foreach (ChecklistPhoto::query()->where('application_id', $application->id)->orderBy('uploaded_at')->orderBy('id')->get() as $photo) {
            $photos[$photo->question_id ?? AnswerRules::GENERAL][] = ['id' => $photo->id];
        }

        return $photos;
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(WorkplaceReview $review): array
    {
        return [
            'decision' => $review->decision->value,
            'decisionLabel' => $review->decision->label(),
            'methodLabel' => $review->method->label(),
            'decidedOn' => $this->day($review->decided_at),
            'visitedOn' => $review->visited_on?->toDateString(),
            'notes' => $review->notes,
            'actionPlan' => $review->action_plan,
            'planDueOn' => $review->plan_due_on?->toDateString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function evidence(Workplace $workplace): array
    {
        $requests = EvidenceRequest::query()->where('workplace_id', $workplace->id)->orderByDesc('requested_at')->orderByDesc('id')->get();
        $photos = [];

        foreach (EvidencePhoto::query()->whereIn('evidence_request_id', $requests->pluck('id')->all())->orderBy('uploaded_at')->orderBy('id')->get() as $photo) {
            $photos[$photo->evidence_request_id][] = ['id' => $photo->id];
        }

        // A fresh get() is already a list, here and in authorizations().
        return array_values($requests->map( // @pest-mutate-ignore: UnwrapArrayValues
            fn (EvidenceRequest $request): array => [
                'id' => $request->id,
                'kind' => $request->kind->value,
                'note' => $request->note,
                'requestedOn' => $this->day($request->requested_at),
                'fulfilled' => $request->fulfilled_at !== null,
                'photos' => $photos[$request->id] ?? [],
            ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function authorizations(Workplace $workplace): array
    {
        $authorizations = VisitAuthorization::query()->where('workplace_id', $workplace->id)->orderByDesc('requested_at')->orderByDesc('id')->get();
        $used = WorkplaceReview::query()->whereIn('visit_authorization_id', $authorizations->pluck('id')->all())->pluck('visit_authorization_id')->all();

        return array_values($authorizations->map( // @pest-mutate-ignore: UnwrapArrayValues
            fn (VisitAuthorization $authorization): array => [
                'id' => $authorization->id,
                'requestedOn' => $this->day($authorization->requested_at),
                'authorized' => $authorization->authorized(),
                'cancelled' => $authorization->cancelled_at !== null,
                'viaPortal' => $authorization->confirmed_at !== null,
                'hasDocument' => $authorization->document_path !== null,
                'used' => in_array($authorization->id, $used, true),
            ])->all());
    }

    /** A calendar date in the company's display time zone. */
    private function day(CarbonInterface $moment): string
    {
        return $moment->setTimezone(config()->string('app.display_timezone'))->toDateString();
    }
}
