<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Interventions\Actions\SendProgramDigests;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Notifications\ProgramDigest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * What HR writes in the Programa (titles, descriptions, analysis, notes, reasons, results, names) may carry a person's name by
 * mistake: it reaches the screen of whoever may read the Programa, and nothing else. Never a log line, the audit trail or an
 * email (CLAUDE.md, «Logging y auditoría»).
 */
const PROGRAM_SECRET_TEXT = 'Juan Pérez Zapata';

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.intervention_files'));
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    ownerOf($this->tenant);
    [$this->campaign, $this->areas] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
});

it('keeps what HR writes out of every log line, the audit trail and the emails', function (): void {
    $base = route('tenant.campaigns.program.store', [$this->tenant, $this->campaign->id]);
    $this->actingAs($this->hr)->post($base)->assertSessionHasNoErrors();
    $program = App\Modules\Interventions\Models\Program::query()->sole();
    $row = ProgramItem::query()->where('program_id', $program->id)->where('scope', 'final')->sole();
    Log::spy();

    $this->put(route('tenant.campaigns.program.responsible', [$this->tenant, $this->campaign->id]), ['responsible_name' => PROGRAM_SECRET_TEXT])->assertSessionHasNoErrors();
    $this->post(route('tenant.campaigns.program.actions.store', [$this->tenant, $this->campaign->id, $row->id]), [
        'kind' => 'policy_review', 'level' => 1, 'title' => 'Plan de '.PROGRAM_SECRET_TEXT, 'description' => 'Hablar con '.PROGRAM_SECRET_TEXT,
        'subjects' => 'areas', 'area_ids' => [$this->areas['Producción']->id], 'due_on' => '2020-01-01', 'responsible_name' => PROGRAM_SECRET_TEXT,
    ])->assertSessionHasNoErrors();
    $action = ProgramAction::query()->sole();
    $this->put(route('tenant.campaigns.program.items.analysis', [$this->tenant, $this->campaign->id, $row->id]), ['analysis' => 'Analiza a '.PROGRAM_SECRET_TEXT])->assertSessionHasNoErrors();
    $this->put(route('tenant.campaigns.program.items.follow-up', [$this->tenant, $this->campaign->id, $row->id]), ['follow_up_done_on' => '2026-11-01', 'follow_up_result' => 'Mejoró '.PROGRAM_SECRET_TEXT])->assertSessionHasNoErrors();
    $this->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $this->campaign->id, $action->id]), [
        'status' => 'in_progress', 'recorded_on' => '2026-11-01', 'note' => 'Se habló con '.PROGRAM_SECRET_TEXT,
        'evidence' => UploadedFile::fake()->createWithContent('lista de '.PROGRAM_SECRET_TEXT.'.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"),
    ])->assertSessionHasNoErrors();
    $entry = App\Modules\Interventions\Models\ProgressEntry::query()->sole();
    $this->get(route('tenant.campaigns.program.evidence', [$this->tenant, $this->campaign->id, $action->id, $entry->id]))->assertOk();
    $this->post(route('tenant.campaigns.program.actions.cancel', [$this->tenant, $this->campaign->id, $action->id]), ['reason' => 'Por culpa de '.PROGRAM_SECRET_TEXT])->assertSessionHasNoErrors();
    resolve(SendProgramDigests::class)->handle();

    $logged = [];
    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) use (&$logged): bool {
        $logged[] = $message.json_encode($context, JSON_THROW_ON_ERROR);

        return true;
    });
    $audited = AuditEntry::query()->get()->map(fn (AuditEntry $entry): string => $entry->event.json_encode($entry->metadata, JSON_THROW_ON_ERROR))->implode("\n");
    $mailed = '';
    Notification::assertSentOnDemand(ProgramDigest::class, function (ProgramDigest $digest, array $channels, AnonymousNotifiable $notifiable) use (&$mailed): bool {
        $mailed .= json_encode($digest->toMail($notifiable)->introLines, JSON_THROW_ON_ERROR);

        return true;
    });

    expect($logged)->not->toBeEmpty()
        ->and(implode("\n", $logged))->not->toContain('Juan')->not->toContain('Zapata')
        ->and($audited)->not->toContain('Juan')->not->toContain('Zapata')->toContain('interventions.program_created')->toContain('interventions.evidence_viewed')->toContain('interventions.action_cancelled')
        ->and($mailed)->not->toContain('Juan')->not->toContain('Zapata');
});

it('shows what HR wrote only to those who read the Programa, in their pages', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.store', [$this->tenant, $this->campaign->id]));
    $program = App\Modules\Interventions\Models\Program::query()->sole();
    $row = ProgramItem::query()->where('program_id', $program->id)->where('scope', 'final')->sole();
    $row->update(['analysis' => 'Analiza a '.PROGRAM_SECRET_TEXT]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('program.items.0.analysis', 'Analiza a '.PROGRAM_SECRET_TEXT));
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertForbidden();
});
