<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\SendProgramDigests;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Notifications\ProgramDigest;
use App\Modules\Interventions\Support\ProgramList;
use App\Modules\Interventions\Support\ProgramPages;
use App\Modules\Interventions\Support\ProgramProgress;
use App\Modules\Results\Support\ResultsPages;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProgramProgress::class, ProgramPages::class, ProgramList::class, ResultsPages::class, SendProgramDigests::class);

/**
 * Criterio C-36 and the request of 9b: one Programa reads the same everywhere. The page, the list of Programas, the card
 * of the results and the Monday email count its actions, its rows with actions and what is overdue from one place.
 */
beforeEach(function (): void {
    // Monday, 9:30 in Mexico City.
    $this->travelTo('2026-11-02 15:30 UTC');
    Notification::fake();
    $this->tenant = App\Modules\Tenancy\Models\Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->program?->update(['responsible_name' => 'Ana', 'responsible_user_id' => $this->owner->id]);
    $this->rows = ProgramItem::query()->where('program_id', $this->program?->id)->orderBy('scope_key')->get();
});

it('reads the same actions, rows with actions, share and state on the page, the list, the results card and the email', function (): void {
    $action = fn (int $row, array $state = []) => ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->rows[$row]->id, ...$state]);
    $action(0, ['status' => 'done', 'completed_on' => '2026-10-20']);
    $action(0, ['status' => 'in_progress', 'due_on' => '2026-10-30']);
    $action(1, ['due_on' => '2026-12-15']);
    $action(1, ['status' => 'cancelled', 'cancelled_reason' => 'Ya no aplica.']);
    $this->rows[0]->update(['follow_up_due_on' => '2027-02-01']);

    $rows = count($this->rows);
    $meter = ['done' => 1, 'total' => 3];
    $coverage = ['covered' => 2, 'total' => $rows];
    // 1 done of: 3 actions, the rows without one and the follow-up that waits.
    $percent = intdiv(100, 3 + ($rows - 2) + 1);
    $state = ['value' => 'overdue', 'label' => 'Con acciones vencidas'];

    expect($rows)->toBeGreaterThan(2);

    $this->actingAs($this->owner)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.meter', $meter)->where('program.coverage', $coverage)->where('program.percent', $percent)
            ->where('program.state', $state)->where('program.overdue', 1));

    $this->actingAs($this->owner)->get(route('tenant.interventions.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('evaluations.data.0.program', ['state' => $state, 'meter' => $meter, 'coverage' => $coverage, 'percent' => $percent]));

    $this->actingAs($this->owner)->get(route('tenant.campaigns.results', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program', ['exists' => true, 'state' => $state, 'meter' => $meter, 'coverage' => $coverage, 'percent' => $percent]));

    resolve(SendProgramDigests::class)->handle();

    Notification::assertSentOnDemand(ProgramDigest::class, function (ProgramDigest $digest, array $channels, AnonymousNotifiable $notifiable) use ($rows): bool {
        $lines = $digest->toMail($notifiable)->introLines;

        return in_array('1 acción vencida.', $lines, true) && in_array(($rows - 2).' filas sin acciones.', $lines, true);
    });
    expect(Program::query()->count())->toBe(1);
});
