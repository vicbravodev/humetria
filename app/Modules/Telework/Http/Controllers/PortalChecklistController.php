<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Telework\Actions\SubmitChecklist;
use App\Modules\Telework\Http\Requests\SubmitChecklistRequest;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PhotoStore;
use App\Modules\Telework\Support\SessionPortal;
use App\Modules\Telework\Support\Settings;
use App\Modules\Telework\Support\WorkplaceAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A person answers one checklist application in their telework section: they read it here and send it once.
 */
final class PortalChecklistController extends Controller
{
    public function show(string $tenant, string $application, SessionPortal $portals, Settings $settings, Nom037Checklist $base, WorkplaceAddress $address): Response|RedirectResponse
    {
        $portal = $portals->current() ?? abort(404);
        $found = $this->application($portal, $application);

        if ($found->submitted_at !== null) {
            return to_route('portal.telework.show', $tenant);
        }

        $questions = ChecklistQuestion::query()->where('checklist_id', $found->checklist_id)->where('section', $found->section->value)->orderBy('position')->get();

        return Inertia::render('telework/portal-checklist', [
            'slug' => $tenant,
            'company' => $portal->company,
            'application' => ['id' => $found->id, 'section' => $found->section->value, 'workplace' => $address->line(Workplace::query()->findOrFail($found->workplace_id))],
            'instructions' => $base->instructions(),
            'notice' => $base->responsibilityNotice(),
            'consent' => ['text' => $settings->currentConsent()->text],
            'questions' => $questions->map(fn (ChecklistQuestion $question): array => [
                'id' => $question->id,
                'heading' => $question->heading,
                'text' => $question->text,
                'favorable' => $question->favorable->value,
                'sensitive' => $question->sensitive,
            ])->all(),
        ]);
    }

    public function store(string $tenant, string $application, SubmitChecklistRequest $request, SessionPortal $portals, SubmitChecklist $submit, PhotoStore $photos): RedirectResponse
    {
        $portal = $portals->current() ?? abort(404);
        $found = $this->application($portal, $application);
        // Before storing: a refused send must not take the company's quota.
        throw_if($found->submitted_at !== null, ValidationException::withMessages(['application' => 'Esta lista ya se envió.']));
        $stored = $photos->storeMany($request->photos(), "checklists/{$portal->tenantId}/{$found->id}");

        $submit->handle($portal, $found->id, $request->boolean('consent'), $request->answers(), $request->riskNotes(), $request->filled('special_needs') ? $request->string('special_needs')->toString() : null, $stored);

        return to_route('portal.telework.show', $tenant);
    }

    /** The application of this person's own workplaces; anything else does not exist for them. */
    private function application(OpenedPortal $portal, string $id): ChecklistApplication
    {
        abort_if($portal->teleworkerId === null, 404);

        return ChecklistApplication::query()->ownedBy($portal->teleworkerId)->findOrFail($id);
    }
}
