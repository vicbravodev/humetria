<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Seating\Actions\SubmitPersonInput;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Http\Requests\PersonInputRequest;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\PrivacyNotice;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * The Ley Silla page of a person's portal, under the same link as the rest. A link that is not valid is a 404 that says
 * nothing about whether it ever existed. The person sees only their own answer.
 */
final class PortalController extends Controller
{
    public function show(string $token, PortalAccess $access, SeatingAssessment $assessment): Response
    {
        $person = $this->person($token, $access);
        $last = PersonInput::query()->where('employee_id', $person->employeeId)->latest('submitted_at')->first(['submitted_at']);
        $zone = config()->string('app.display_timezone');
        $current = RiskAssessment::query()->where('employee_id', $person->employeeId)->where('status', AssessmentStatus::Signed)->latest('signed_at')->first();
        $confirmed = $current === null ? null : RiskAcknowledgment::query()->where('assessment_id', $current->id)->first();

        return Inertia::render('seating/portal', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'notice' => PrivacyNotice::text(),
            'footwear' => $this->condition($assessment, 'footwear'),
            'discomfort' => $this->condition($assessment, 'discomfort'),
            'sentOn' => $last?->submitted_at->setTimezone($zone)->toDateString(),
            // What the person is asked to confirm: the current signed assessment and its measures, until they do.
            'acknowledgment' => $current === null || $confirmed !== null ? null : [
                'assessmentId' => $current->id,
                'level' => $current->level?->value,
                'levelLabel' => $current->level?->label(),
                'signedOn' => $current->signed_at?->setTimezone($zone)->toDateString(),
                'measures' => $this->measuresOf($current),
            ],
            'acknowledgedOn' => $confirmed?->acknowledged_at->setTimezone($zone)->toDateString(),
        ]);
    }

    public function store(string $token, PersonInputRequest $request, PortalAccess $access, SubmitPersonInput $submit): RedirectResponse
    {
        $person = $this->person($token, $access);
        $submit->handle($person, $request->integer('footwear'), $request->integer('discomfort'), $request->note());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Recibimos sus respuestas.']);

        return to_route('portal.seating.show', $token);
    }

    /**
     * The measures that apply to the person: those of their assessment and those of their position, without the cancelled ones.
     *
     * @return list<string>
     */
    private function measuresOf(RiskAssessment $assessment): array
    {
        $kinds = PreventiveMeasure::query()->where('status', '!=', MeasureStatus::Cancelled)
            ->where(fn ($query) => $query->where('assessment_id', $assessment->id)->orWhere(fn ($own) => $own->whereNull('assessment_id')->where('position_id', $assessment->position_id)))
            ->orderBy('created_at')->get(['kind'])->map(fn (PreventiveMeasure $measure): string => $measure->kind->label());

        return array_values($kinds->unique()->all());
    }

    private function person(string $token, PortalAccess $access): PortalIdentity
    {
        $person = $access->open($token);
        abort_if($person === null, 404);

        return $person;
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
