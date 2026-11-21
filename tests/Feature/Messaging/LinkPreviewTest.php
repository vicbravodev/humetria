<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * WhatsApp (and any chat app) fetches a link to draw its preview: opening the link must never spend it. The token is spent by the
 * POST that submits, so a preview is harmless. Production runs SESSION_DRIVER=database, which writes the `sessions` row and nothing else.
 */

/**
 * The tables written by the statements run while `$visit` runs.
 *
 * @return list<string>
 */
function tablesWrittenBy(Closure $visit): array
{
    $written = [];
    DB::listen(function (QueryExecuted $query) use (&$written): void {
        if (preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+"?([a-z_]+)"?/i', $query->sql, $match) === 1) {
            $written[] = $match[1];
        }
    });

    $visit();

    return array_values(array_unique($written));
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    config(['session.driver' => 'database']);
});

it('does not spend the NOM-035 survey link by opening it', function (): void {
    $tenant = Tenant::factory()->create();
    $participant = Participant::factory()->create([
        'campaign_id' => Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $tenant->id])->id,
        'token_hash' => Participant::hashToken('the-token'),
    ]);
    ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => 'nom035.guide-ii']);

    $written = tablesWrittenBy(fn () => $this->get(route('survey.show', 'the-token'))->assertOk());

    expect(array_diff($written, ['sessions']))->toBe([]);
    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('unavailable', null));
});

it('does not spend the climate survey link by opening it', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $survey = Survey::factory()->active('2026-11-30')->create(['tenant_id' => $tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    $invitation = Invitation::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'employee_id' => $employee->id]);
    $invitation->update(['token_hash' => Invitation::hashToken($invitation->surveyToken())]);
    $token = $invitation->surveyToken();

    $written = tablesWrittenBy(fn () => $this->get(route('engagement.survey.show', $token))->assertOk());

    expect(array_diff($written, ['sessions']))->toBe([]);
    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', null));
});
