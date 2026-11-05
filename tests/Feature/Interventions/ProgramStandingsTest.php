<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Contracts\ProgramStandings;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\CompanyProgramStandings;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;

mutates(CompanyProgramStandings::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('tells where the Programa of each evaluation stands, and leaves out the evaluations without one', function (): void {
    $empty = Program::factory()->create(['tenant_id' => $this->tenant->id]);
    ProgramItem::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $empty->id]);
    $running = Program::factory()->create(['tenant_id' => $this->tenant->id]);
    ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $running->id]);
    $finished = Program::factory()->create(['tenant_id' => $this->tenant->id]);
    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $finished->id]);
    $without = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);

    expect(app(ProgramStandings::class)->forEvaluations([$empty->campaign_id, $running->campaign_id, $finished->campaign_id, $without->id]))->toBe([
        $empty->campaign_id => 'none',
        $running->campaign_id => 'in_progress',
        $finished->campaign_id => 'done',
    ])->and(app(ProgramStandings::class)->forEvaluations([]))->toBe([]);
});
