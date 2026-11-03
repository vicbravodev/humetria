<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\ChangePageLinkExpiry;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Actions\PublishPolicy;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\RecordDissemination;
use App\Modules\Disclosure\Actions\RecordManualAcknowledgments;
use App\Modules\Disclosure\Actions\RotatePageLink;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartContentDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('writes nothing of a person, a credential or a text the company wrote in the logs or the audit trail, through the whole life of the disclosure', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Secreta Norte']);
    $ana = Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana Nombre Privado', 'email' => 'ana.privada@acme.test']);
    workIn($tenant);

    $draft = resolve(StartPolicyDraft::class)->handle($hr->id);
    resolve(SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => ['key' => $section['key'], 'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'FRASE-SECRETA-DE-LA-POLITICA' : $section['text'], 'items' => $section['items']], $draft->content));
    $policy = resolve(PublishPolicy::class)->handle($draft->id, null, ['a', 'b', 'c'], true, $hr->id);
    $block = resolve(StartContentDraft::class)->handle(DocumentKind::ViolenceMeasures, $hr->id);
    resolve(SaveDraft::class)->handle($block->id, [['key' => 'body', 'text' => 'FRASE-SECRETA-DEL-BLOQUE', 'items' => []]]);
    resolve(PublishContent::class)->handle($block->id, null, $hr->id);
    $personal = resolve(PortalAccess::class)->token($ana->id);
    $page = (string) resolve(IssuePageLink::class)->handle($hr->id)->token();
    $this->post(route('portal.policy.acknowledge', $personal), ['document' => $policy->id]);
    resolve(RecordManualAcknowledgments::class)->handle($policy->id, [$ana->id], '2026-11-20', 'NOTA-PRIVADA-DE-LA-HOJA', $hr->id);
    resolve(RecordDissemination::class)->handle($plant->id, 'poster', ['a'], '2026-11-20', 'NOTA-DEL-CARTEL', $hr->id);
    resolve(ChangePageLinkExpiry::class)->handle($hr->id, '2027-06-01');
    $this->get(route('workers.show', $page));
    $this->actingAs($hr)->get(route('tenant.disclosure.page', $tenant));
    $this->actingAs($hr)->get(route('tenant.disclosure.dossier', [$tenant, $plant->id]));
    $rotated = (string) resolve(RotatePageLink::class)->handle($hr->id)->token();
    [$campaign] = closedEvaluationInAreas($tenant, ['Área Privada' => 12]);
    $campaign->update(['work_center_id' => $plant->id]);
    resolve(PublishResults::class)->handle($campaign->id, $hr->id, true);

    $everything = implode("\n", $logged).json_encode(AuditEntry::query()->get(['event', 'metadata', 'subject_id'])->all());

    expect($logged)->not->toBeEmpty()
        ->and(AuditEntry::query()->where('event', 'like', 'disclosure.%')->count())->toBeGreaterThan(5);
    foreach ([$page, $personal, $rotated, 'ana.privada@acme.test', 'Ana Nombre Privado', 'FRASE-SECRETA-DE-LA-POLITICA', 'FRASE-SECRETA-DEL-BLOQUE', 'NOTA-PRIVADA-DE-LA-HOJA', 'NOTA-DEL-CARTEL', 'Planta Secreta Norte', 'Área Privada'] as $secret) {
        expect($everything)->not->toContain($secret);
    }
});

it('ties no table of the disclosure to the answers of a questionnaire, an invitation or a token of the survey', function (): void {
    $answers = ['anonymous_responses', 'trauma_screenings', 'participants', 'participant_instruments', 'screening_referrals'];
    $targets = DB::table('information_schema.table_constraints as tc')
        ->join('information_schema.constraint_column_usage as ccu', 'tc.constraint_name', '=', 'ccu.constraint_name')
        ->where('tc.constraint_type', 'FOREIGN KEY')
        ->where('tc.table_name', 'like', 'disclosure_%')
        ->distinct()->pluck('ccu.table_name')->all();

    expect($targets)->not->toBeEmpty()->and(array_intersect($targets, $answers))->toBe([]);
});

it('keeps the whole public surface free of the personal data of the company', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $owner = ownerOf($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Sur']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana Nombre Privado', 'email' => 'ana.privada@acme.test']);
    workIn($tenant);
    $token = (string) resolve(IssuePageLink::class)->handle($owner->id)->token();

    foreach ([route('workers.show', $token), route('workers.center', [$token, $plant->id])] as $url) {
        $json = json_encode($this->get($url)->viewData('page')['props']) ?: '';

        expect($json)->not->toContain('Ana Nombre Privado')->not->toContain('ana.privada')->not->toContain($owner->email)->not->toContain('employee');
    }
});
