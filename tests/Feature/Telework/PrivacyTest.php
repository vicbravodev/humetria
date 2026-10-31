<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
 * NOM-037 keeps health-adjacent answers, notes and homes. None of it, and nothing that names the person, may reach a log
 * line or the audit trail: both are read by people (and tools) that must not see it.
 */

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    $this->logPath = storage_path('logs/privacy-test.log');
    File::delete($this->logPath);
    // Every channel the app can log to writes here, as it would to the daily file or stderr in production.
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath, 'logging.channels.stack.channels' => ['single']]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
});

afterEach(function (): void {
    File::delete($this->logPath);
});

it('never puts NOM-037 personal data or answers in a log line or audit entry', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $cora = memberOf($tenant, TenantRole::Committee);
    $center = WorkCenter::factory()->for($tenant)->create();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Zulema Quintanilla', 'email' => 'zulema.privada@acme.test']);
    workIn($tenant);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'user_id' => $cora->id]);
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $risk = addQuestion($checklist, 1, 'yes');
    $health = addQuestion($checklist, 2, 'yes', sensitive: true);

    $secrets = [
        'name' => 'Zulema', 'surname' => 'Quintanilla', 'email' => 'zulema.privada', 'street' => 'Calle Secretisima', 'exterior' => '7731',
        'phone' => '5598761234', 'ip' => '203.0.113.77', 'risk_note' => 'NOTA-DE-RIESGO-XYZ', 'special_needs' => 'NECESIDAD-ESPECIAL-XYZ',
        'review_notes' => 'NOTAS-DE-REVISION-XYZ', 'plan' => 'PLAN-DE-ACCION-XYZ', 'notice' => 'DESCRIPCION-DEL-AVISO-XYZ', 'refusal' => 'MOTIVO-DE-RECHAZO-XYZ',
        'ack_note' => 'NOTA-DE-ACUSE-XYZ', 'review_note' => 'NOTA-DE-REVISION-DEL-AVISO-XYZ', 'equipment' => 'EQUIPO-DESCRIPCION-XYZ', 'activities' => 'ACTIVIDADES-XYZ',
    ];
    $home = ['street' => $secrets['street'], 'exterior_number' => $secrets['exterior'], 'interior_number' => '', 'neighborhood' => 'Centro', 'postal_code' => '76000', 'municipality' => 'Querétaro', 'state' => 'QUE'];

    // RH registers the aspirant, with the home they would work from, and the equipment delivered.
    $this->actingAs($hr)->post(route('tenant.telework.teleworkers.store', [$tenant, $center->id]), ['employee_id' => $employee->id, 'telework_percentage' => 60, 'workplaces' => [$home]])->assertSessionHasNoErrors();
    $person = Teleworker::query()->sole();
    $this->post(route('tenant.telework.teleworkers.equipment.store', [$tenant, $person->id]), ['kind' => 'chair', 'description' => $secrets['equipment'], 'delivered_on' => now()->toDateString()])->assertSessionHasNoErrors();

    // The person answers Sección I with an unfavorable answer, a note, special needs and a photo.
    $token = resolve(IssuePortalLink::class)->handle($employee->id);
    $application = ChecklistApplication::query()->sole();
    $photo = new UploadedFile(__DIR__.'/../../Fixtures/photo.png', 'p.png', null, null, true);
    $this->withServerVariables(['REMOTE_ADDR' => $secrets['ip']])->post(route('portal.checklist.store', [$token, $application->id]), [
        'consent' => '1', 'answers' => [$risk->id => 'no', $health->id => 'no'], 'risk_notes' => [$risk->id => $secrets['risk_note'], $health->id => $secrets['risk_note']],
        'special_needs' => $secrets['special_needs'], "photo_{$risk->id}_0" => $photo, 'photo_general_0' => $photo,
    ])->assertSessionHasNoErrors();

    // The Comisión reviews it with notes and a plan; RH then admits the person to the roster.
    $this->actingAs($cora)->post(route('tenant.telework.reviews.store', [$tenant, $application->id]), [
        'decision' => 'valid_with_plan', 'notes' => $secrets['review_notes'], 'action_plan' => $secrets['plan'], 'plan_due_on' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();
    $this->actingAs($hr)->post(route('tenant.telework.teleworkers.join', [$tenant, $person->id]), [
        'marital_status' => 'married', 'activities' => $secrets['activities'], 'contact_phone' => $secrets['phone'], ...$home,
    ])->assertSessionHasNoErrors();

    // The person refuses the equipment with a reason and gives a written notice.
    $item = EquipmentItem::query()->sole();
    $this->withServerVariables(['REMOTE_ADDR' => $secrets['ip']])->post(route('portal.equipment.respond', [$token, $item->id]), ['received' => '0', 'reason' => $secrets['refusal']])->assertSessionHasNoErrors();
    $this->withServerVariables(['REMOTE_ADDR' => $secrets['ip']])->post(route('portal.notices.store', $token), ['kind' => 'work_risk', 'written_on' => now()->toDateString(), 'occurred_on' => now()->toDateString(), 'description' => $secrets['notice']])->assertSessionHasNoErrors();
    $notice = WorkerNotice::query()->sole();
    $this->actingAs($hr)->post(route('tenant.telework.notices.review', [$tenant, $notice->id]), ['note' => $secrets['review_note']])->assertSessionHasNoErrors();

    // RH records a paper acknowledgement with a note; the person also confirms the document from the portal.
    $document = TeleworkDocumentFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $this->post(route('tenant.telework.documents.acknowledgements.store', [$tenant, $document->id]), ['employee_ids' => [$employee->id], 'date' => now()->toDateString(), 'note' => $secrets['ack_note']])->assertSessionHasNoErrors();
    $this->post(route('tenant.telework.documents.acknowledgements.store', [$tenant, $document->id]), ['employee_ids' => [$employee->id], 'date' => now()->toDateString(), 'note' => $secrets['ack_note']]);
    $this->withServerVariables(['REMOTE_ADDR' => $secrets['ip']])->post(route('portal.documents.acknowledge', [$token, $document->id]))->assertSessionHasNoErrors();

    // Looking at the dossier and the pages RH uses is logged too.
    $this->get(route('tenant.telework.dossier', [$tenant, $center->id]))->assertOk();
    $this->get(route('tenant.telework.center', [$tenant, $center->id]))->assertOk();

    $logs = File::get($this->logPath);
    $audits = AuditEntry::query()->get()->map(fn (AuditEntry $entry): string => json_encode([$entry->event, $entry->metadata], JSON_THROW_ON_ERROR))->implode("\n");

    expect($logs)->toContain('telework.aspirant_registered')->toContain('telework.workplace_reviewed')->toContain('telework.checklist_submitted')
        ->and($audits)->toContain('telework.');

    foreach ($secrets as $label => $secret) {
        expect($logs)->not->toContain($secret, "log line leaks {$label}")
            ->and($audits)->not->toContain($secret, "audit entry leaks {$label}");
    }
});
