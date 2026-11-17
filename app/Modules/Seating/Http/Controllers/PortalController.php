<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Seating\Actions\SubmitPersonInput;
use App\Modules\Seating\Http\Requests\PersonInputRequest;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Support\PortalAssessment;
use App\Modules\Seating\Support\PrivacyNotice;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * The Ley Silla pages of the person of the session (finding 13): the list of their tasks, and the two questions on a page of
 * their own. The person sees only their own answer; neither page names them.
 */
final class PortalController extends Controller
{
    public function show(string $tenant, WorkerSession $session): Response
    {
        $person = $session->current() ?? abort(404);
        $assessment = new PortalAssessment($person->employeeId);

        return Inertia::render('seating/portal', [
            'slug' => $tenant,
            'company' => $person->companyName,
            'homeUrl' => route('worker.home', $tenant),
            'pending' => $assessment->pending(),
            'acknowledgedOn' => $assessment->acknowledgedOn(),
            'sentOn' => $this->sentOn($person->employeeId),
        ]);
    }

    public function answers(string $tenant, WorkerSession $session, SeatingAssessment $assessment): Response
    {
        $person = $session->current() ?? abort(404);

        return Inertia::render('seating/portal-answers', [
            'slug' => $tenant,
            'company' => $person->companyName,
            'notice' => PrivacyNotice::text(),
            'footwear' => $this->condition($assessment, 'footwear'),
            'discomfort' => $this->condition($assessment, 'discomfort'),
            'sentOn' => $this->sentOn($person->employeeId),
        ]);
    }

    public function store(string $tenant, PersonInputRequest $request, WorkerSession $session, SubmitPersonInput $submit): RedirectResponse
    {
        $person = $session->current() ?? abort(404);
        $submit->handle($person, $request->integer('footwear'), $request->integer('discomfort'), $request->note());

        return to_route('portal.seating.answers', $tenant);
    }

    /** The date of the person's last answer, if they ever sent one. */
    private function sentOn(string $employeeId): ?string
    {
        $last = PersonInput::query()->where('employee_id', $employeeId)->latest('submitted_at')->first(['submitted_at']);

        return $last?->submitted_at->setTimezone(config()->string('app.display_timezone'))->toDateString();
    }

    /**
     * @return array{title: string, options: array<int, string>}
     */
    private function condition(SeatingAssessment $assessment, string $key): array
    {
        foreach ($assessment->definition()->conditions as $condition) {
            if ($condition->key === $key) {
                return ['title' => $condition->title, 'options' => $condition->options];
            }
        }

        throw new LogicException("The instrument has no condition {$key}.");
    }
}
