<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;

/**
 * Acme with Planta Querétaro, where Hugo Saiz teleworks and reported an accident on 27 Feb 2026.
 *
 * @return array{0: Tenant, 1: App\Modules\Telework\Models\WorkerNotice, 2: string}
 */
function accidentCompany(array $notice = []): array
{
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $hugo = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Hugo Saiz', 'job_title' => 'Almacenista']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $hugo->id]);
    workIn($tenant);

    return [$tenant, WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $person->id, ...$notice]), $hugo->id];
}

// Board V3-Aviso: once RH records what was done, the person reads it in their portal.
it('shows the person in their portal what was done about their accident', function (bool $dark, bool $mobile): void {
    [$tenant, , $employeeId] = accidentCompany(['reviewed_by' => memberOf(Tenant::factory()->create(), TenantRole::Hr)->id, 'reviewed_at' => '2026-03-02 12:00:00', 'review_actions' => ['medical_care', 'leave'], 'review_note' => 'Se acordó no cargar más de 10 kg.']);
    $token = resolve(IssuePortalLink::class)->handle($employeeId);

    visitPage('telework/portal-notices', "/portal/{$token}/notices", $dark, $mobile)
        ->assertSee('Se canalizó a atención médica')
        ->assertSee('Se le dio un permiso para recuperarse')
        ->assertSee('Se acordó no cargar más de 10 kg.');

    expect($tenant->slug)->toBe('acme');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

// Board V3-Aviso: an accident nobody answered, with its three steps.
it('shows an accident to follow up as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    [$tenant, $notice] = accidentCompany();
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    visitPage('telework/notice', "/admin/acme/telework/notices/{$notice->id}", $dark, $mobile)
        ->assertSee('Hugo Saiz avisó un accidente de trabajo')
        ->assertSee('«Me lastimé la espalda al levantar una caja.»')
        ->assertSee('Registra qué se hizo')
        ->assertSee('Guardar y avisar a Hugo Saiz')
        ->assertSee('Línea de tiempo')
        ->assertSee('¿Hay que revisar su lugar?')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('records what was done, tells the person and closes the accident with its evidence', function (): void {
    Illuminate\Support\Facades\Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    [$tenant, $notice] = accidentCompany();
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    visit("/admin/acme/telework/notices/{$notice->id}")
        ->assertButtonDisabled('Guardar y avisar a Hugo Saiz')
        ->click('Se ajustó su lugar o equipo')
        ->fill('#accident-note', 'Se le entregó una silla nueva.')
        ->press('Guardar y avisar a Hugo Saiz')
        ->assertSee('Enviado a su portal')
        ->assertSee('En seguimiento')
        ->attach('evidence', __DIR__.'/../Fixtures/policy.pdf')
        ->press('Cerrar aviso')
        ->assertSee('Ver la evidencia')
        ->assertSee('Cerrado')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($notice->refresh()->closed_at)->not->toBeNull()->and($notice->review_actions)->toBe(['place_adjusted']);
});

it('opens an accident from the list of notices and from the panel', function (): void {
    [$tenant] = accidentCompany();
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    visit('/admin/acme/telework/notices')
        ->click('Revisar aviso')
        ->assertSee('Registra qué se hizo')
        ->assertNoJavaScriptErrors();

    visit('/admin/acme')
        ->click('[aria-labelledby="urgent"] a')
        ->assertSee('Registra qué se hizo')
        ->assertNoJavaScriptErrors();
});
