<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\AuditPages;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AuditPages::class);

/** One audit row, written straight to the table: its time and who did it are the point of each test. */
function auditRow(string $event, array $attributes = []): AuditEntry
{
    return AuditEntry::query()->forceCreate([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'event' => $event,
        'metadata' => [],
        'created_at' => now(),
        ...$attributes,
    ]);
}

it('lives at /admin/audit', function (): void {
    expect(route('audit.index', absolute: false))->toBe('/admin/audit');
});

it('sends guests to the login', function (): void {
    $this->get(route('audit.index'))->assertRedirect(route('login'));
});

it('answers 403 to support and to an owner: only the superadmin reads the audit trail', function (User $user): void {
    auditRow('tenant.created');

    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('lists the entries newest first, 50 to a page, with who acted and in which company', function (): void {
    $this->travelTo('2026-11-12 18:00 UTC');
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Demo']);
    $actor = User::factory()->create(['name' => 'Ana López', 'email' => 'ana@acme.mx']);
    auditRow('campaign.launched', ['tenant_id' => $tenant->id, 'actor_id' => $actor->id, 'metadata' => ['work_center' => 'Planta', 'size' => 12], 'created_at' => now()->subHour()]);
    auditRow('tenant.created', ['created_at' => now()->subDay()]);

    $this->actingAs(superadmin())->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/audit')
            ->where('entries.total', 2)
            ->where('entries.per_page', 50)
            ->where('entries.data.0.event', 'campaign.launched')
            ->where('entries.data.0.at', '12 nov 2026, 11:00')
            ->where('entries.data.0.actor', ['name' => 'Ana López', 'email' => 'ana@acme.mx'])
            ->where('entries.data.0.impersonator', null)
            ->where('entries.data.0.company', ['id' => $tenant->id, 'name' => 'Acme Demo'])
            ->where('entries.data.0.context', ['Work center' => 'Planta', 'Size' => '12'])
            ->where('entries.data.1.event', 'tenant.created')
            ->where('entries.data.1.actor', null)
            ->where('entries.data.1.company', null));
});

it('pages after fifty entries', function (): void {
    foreach (range(1, 51) as $minute) {
        auditRow('tenant.updated', ['created_at' => now()->subMinutes($minute)]);
    }

    $this->actingAs(superadmin())->get(route('audit.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('entries.current_page', 2)->has('entries.data', 1));
});

it('filters by family of event, by prefix and not by what merely contains it', function (): void {
    auditRow('billing.payment_recorded');
    auditRow('tenant.created');
    auditRow('tenant_member.removed');

    $this->actingAs(superadmin())->get(route('audit.index', ['family' => 'tenant']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.event', 'tenant.created')
            ->where('filters.family', 'tenant'));
});

it('splits the trail in views by what matters most, each with how many it holds under the other filters', function (string $kind, array $events): void {
    $acme = Tenant::factory()->create();
    foreach ([
        'impersonation.started', 'impersonation.stopped',
        'billing.payment_recorded', 'tenant.trial_started', 'tenant.module_granted',
        'results.viewed', 'engagement.results_exported', 'results.share_opened',
        'tenant_role.created', 'tenant_member.role_changed', 'user.support_granted',
        'tenant.created',
        // Reviewed is not viewed: it stays out of the results.
        'telework.notice_reviewed', 'attendance.offline_mark_reviewed',
    ] as $event) {
        auditRow($event, ['tenant_id' => $acme->id]);
    }
    auditRow('billing.payment_recorded'); // another company's: outside the filter, outside the counts

    $this->actingAs(superadmin())->get(route('audit.index', ['kind' => $kind, 'company' => $acme->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.kind', $kind)
            ->where('counts', ['all' => 14, 'support' => 2, 'billing' => 3, 'results' => 3, 'roles' => 3])
            ->where('entries.data', fn ($rows): bool => collect($rows)->pluck('event')->sort()->values()->all() === $events));
})->with([
    'support' => ['support', ['impersonation.started', 'impersonation.stopped']],
    'billing and plans' => ['billing', ['billing.payment_recorded', 'tenant.module_granted', 'tenant.trial_started']],
    'results seen or exported' => ['results', ['engagement.results_exported', 'results.share_opened', 'results.viewed']],
    'roles and permissions' => ['roles', ['tenant_member.role_changed', 'tenant_role.created', 'user.support_granted']],
]);

it('shows everything for a view it does not know', function (): void {
    auditRow('tenant.created');

    $this->actingAs(superadmin())->get(route('audit.index', ['kind' => 'x%']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.kind', '')->where('entries.total', 1));
});

it('ignores a family that is not in the catalog', function (): void {
    auditRow('tenant.created');
    auditRow('billing.payment_recorded');

    $this->actingAs(superadmin())->get(route('audit.index', ['family' => 'x%']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 2)->where('filters.family', ''));
});

it('filters by company', function (): void {
    $acme = Tenant::factory()->create();
    $beta = Tenant::factory()->create();
    auditRow('tenant.updated', ['tenant_id' => $acme->id]);
    auditRow('tenant.updated', ['tenant_id' => $beta->id]);
    auditRow('tenant.updated');

    $this->actingAs(superadmin())->get(route('audit.index', ['company' => $acme->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.company.id', $acme->id));
});

it('finds the companies to narrow the trail to on the server, by any part of their name', function (): void {
    $acme = Tenant::factory()->create(['trade_name' => 'Aceros Monterrey']);
    Tenant::factory()->create(['trade_name' => 'Hotel Vía Real']);

    $this->actingAs(superadmin())->getJson(route('audit.companies', ['q' => 'monterrey']))
        ->assertOk()
        ->assertExactJson([['value' => $acme->id, 'label' => 'Aceros Monterrey']]);
});

it('gives the companies of the filter to the superadmin only', function (User $user): void {
    $this->actingAs($user)->getJson(route('audit.companies'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('gives at most twenty companies at a time, in order of name', function (): void {
    Tenant::factory()->count(21)->sequence(fn ($sequence) => ['trade_name' => sprintf('Empresa %02d', $sequence->index)])->create();

    $this->actingAs(superadmin())->getJson(route('audit.companies'))
        ->assertJsonCount(20)
        ->assertJsonPath('0.label', 'Empresa 00');
});

it('filters by a range of local days, both edges included', function (): void {
    // Mexico City is UTC-6 in November: the 10th local runs from 06:00 UTC to 05:59:59 UTC the next day.
    auditRow('tenant.updated', ['metadata' => ['mark' => 'before'], 'created_at' => '2026-11-10 05:59:59+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'first'], 'created_at' => '2026-11-10 06:00:00+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'last'], 'created_at' => '2026-11-12 05:59:59+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'after'], 'created_at' => '2026-11-12 06:00:00+00']);

    $this->actingAs(superadmin())->get(route('audit.index', ['from' => '2026-11-10', 'to' => '2026-11-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 2)
            ->where('filters.from', '2026-11-10')
            ->where('filters.to', '2026-11-11'));
});

it('counts an entry of tonight, Mexico time, in a range that ends today', function (): void {
    // 20:00 on the 16th in Mexico City is 02:00 UTC on the 17th; 23:00 UTC on the 15th is still the 15th in Mexico.
    $this->travelTo(CarbonImmutable::parse('2026-11-16 21:00:00', 'America/Mexico_City'));
    auditRow('tenant.updated', ['created_at' => '2026-11-17 02:00:00+00']);
    auditRow('tenant.created', ['created_at' => '2026-11-15 23:00:00+00']);

    $this->actingAs(superadmin())->get(route('audit.index', ['from' => '2026-11-16', 'to' => '2026-11-16']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.event', 'tenant.updated'));
});

it('ignores a date that is not a date', function (): void {
    auditRow('tenant.updated');

    $this->actingAs(superadmin())->get(route('audit.index', ['from' => 'ayer', 'to' => '2026-13-45']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 1)->where('filters.from', '')->where('filters.to', ''));
});

it('filters by the email of whoever acted', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    $luis = User::factory()->create(['email' => 'luis@beta.mx']);
    auditRow('tenant.updated', ['actor_id' => $ana->id]);
    auditRow('tenant.updated', ['actor_id' => $luis->id]);

    $this->actingAs(superadmin())->get(route('audit.index', ['q' => 'ANA@']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.actor.email', 'ana@acme.mx'));
});

it('searches the email as text, not as a pattern', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    auditRow('tenant.updated', ['actor_id' => $ana->id]);

    $this->actingAs(superadmin())->get(route('audit.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
});

it('shows who was impersonating on an entry made while impersonating', function (): void {
    $owner = User::factory()->create(['name' => 'Dueña', 'email' => 'duena@acme.mx']);
    $staff = User::factory()->create(['name' => 'Soporte Uno', 'email' => 'uno@humetria.mx']);
    auditRow('campaign.launched', ['actor_id' => $owner->id, 'impersonator_id' => $staff->id]);

    $this->actingAs(superadmin())->get(route('audit.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.actor.email', 'duena@acme.mx')
            ->where('entries.data.0.impersonator', ['name' => 'Soporte Uno', 'email' => 'uno@humetria.mx']));
});

it('reads a page in the same number of queries however many entries it holds', function (): void {
    $this->actingAs(superadmin());
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('audit.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    $count(); // the first request also fills the permission cache
    foreach (range(1, 2) as $n) {
        auditRow('tenant.updated', ['actor_id' => User::factory()->create()->id, 'tenant_id' => Tenant::factory()->create()->id, 'impersonator_id' => User::factory()->create()->id]);
    }
    $few = $count();
    foreach (range(1, 12) as $n) {
        auditRow('tenant.updated', ['actor_id' => User::factory()->create()->id, 'tenant_id' => Tenant::factory()->create()->id, 'impersonator_id' => User::factory()->create()->id]);
    }

    expect($count())->toBe($few);
});

it('exports what the filters find as CSV, newest first, and records the export in the trail itself', function (): void {
    $acme = Tenant::factory()->create(['trade_name' => 'Acme']);
    // A name a spreadsheet would run as a formula goes out as text.
    $ana = User::factory()->create(['name' => '=Ana López', 'email' => 'ana@acme.mx']);
    auditRow('tenant.updated', ['tenant_id' => $acme->id, 'actor_id' => $ana->id, 'created_at' => now()->subHour(), 'metadata' => ['reason' => 'Cambio de razón social']]);
    auditRow('tenant.created', ['tenant_id' => $acme->id, 'metadata' => ['changes' => ['plan' => ['before' => 'trial', 'after' => 'integral']]]]);
    auditRow('billing.payment_recorded', ['tenant_id' => $acme->id]);
    $admin = superadmin();
    Log::spy();

    $response = $this->actingAs($admin)->get(route('audit.export', ['family' => 'tenant', 'company' => $acme->id]));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $lines = explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->streamedContent())));
    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe('"Fecha y hora",Evento,Quién,Correo,"Actuó como",Empresa,Detalle,Cambio')
        ->and($lines[1])->toContain('tenant.created')->toContain('Plan: En prueba → Integral')
        ->and($lines[2])->toContain('tenant.updated')->toContain("'=Ana López")->toContain('ana@acme.mx')->toContain('Motivo: Cambio de razón social');

    $entry = AuditEntry::query()->where('event', 'audit.exported')->sole();
    expect($entry->actor_id)->toBe($admin->id)
        ->and($entry->tenant_id)->toBe($acme->id)
        ->and($entry->metadata)->toMatchArray(['family' => 'tenant', 'rows' => 2]);
    Log::shouldHaveReceived('info')->with('audit.exported', Mockery::on(fn (array $context): bool => $context['rows'] === 2));

    $this->get(route('audit.index', ['family' => 'audit']))->assertInertia(fn (Assert $page) => $page
        ->where('filters.family', 'audit')
        ->where('entries.data.0.event', 'audit.exported')
        ->where('entries.data.0.context', ['Empresa' => 'Acme', 'Tipo de evento' => 'tenant', 'Filas' => '2']));
});

it('lets only the superadmin export the trail', function (User $user): void {
    $this->actingAs($user)->get(route('audit.export'))->assertForbidden();

    expect(AuditEntry::query()->where('event', 'audit.exported')->exists())->toBeFalse();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('has no route that changes or deletes an entry', function (): void {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/audit') && array_diff($route->methods(), ['GET', 'HEAD']) !== []);

    expect($writes)->toBeEmpty();
});

it('is forbidden while impersonating', function (): void {
    expect(ForbidWhileImpersonating::guards('audit.index'))->toBeTrue();
});

it('offers the trail of a company from its page to the superadmin only', function (User $user, bool $offered): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)->get(route('companies.show', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('can_audit', $offered));
})->with([
    'superadmin' => [fn () => superadmin(), true],
    'support' => [fn () => support(), false],
]);

it('writes the detail of an entry with labelled fields, company and people by name and yes or no', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Demo']);
    $ana = User::factory()->create(['name' => 'Ana López', 'email' => 'ana@acme.mx']);
    auditRow('tenant.suspended', ['metadata' => ['tenant_id' => $tenant->id, 'user_id' => $ana->id, 'reason' => 'Falta de pago', 'created' => true, 'unlabelled_flag' => false]]);

    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', [
            'Empresa' => 'Acme Demo',
            'Persona' => 'Ana López (ana@acme.mx)',
            'Motivo' => 'Falta de pago',
            'Alta nueva' => 'Sí',
            'Unlabelled flag' => 'No',
        ]));
});

it('writes the values of the detail as a person reads them: states, dates in Mexico City, pesos, modules by name and nothing as null', function (): void {
    auditRow('billing.subscription_set', ['metadata' => [
        'plan' => 'integral',
        'profile' => 'direct',
        'status' => 'active',
        'previous' => 'read_only',
        'negotiated_price_cents' => 1234550,
        'active_until' => '2026-11-27T05:59:59Z',
        'starts_on' => '2026-11-26',
        'reason' => null,
        'enabled' => ['pulse', 'org_chart'],
        'pending' => ['plan' => 'cumplimiento', 'seats' => 10, 'effective_on' => '2026-12-01'],
    ]]);

    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', [
            'Plan' => 'Integral',
            'Perfil de precio' => 'Directo',
            'Estado' => 'Activa',
            'Estado anterior' => 'Solo lectura',
            'Precio negociado' => '$12,345.50',
            'Activa hasta' => '26 nov 2026',
            'Empieza el' => '26 nov 2026',
            'Motivo' => '—',
            'Herramientas activadas' => 'Pulso, Organigrama',
            'Cambio programado' => 'Plan: Cumplimiento, Lugares: 10, Vigente desde: 1 dic 2026',
        ]));
});

it('writes the change of an entry as before and after, field by field, and only what changed', function (): void {
    auditRow('employee.updated', ['metadata' => ['changes' => [
        'job_title' => ['before' => 'Analista', 'after' => 'Gerente'],
        'area_id' => ['before' => null, 'after' => 'a1'],
    ]]]);
    auditRow('attendance.schedule_saved', ['created_at' => now()->subMinute(), 'metadata' => [
        'before' => ['shift_type' => 'day', 'work_mode' => 'onsite'],
        'after' => ['shift_type' => 'night', 'work_mode' => 'onsite'],
    ]]);
    auditRow('attendance.schedule_saved', ['created_at' => now()->subMinutes(2), 'metadata' => [
        'before' => null,
        'after' => ['shift_type' => 'mixed'],
    ]]);
    auditRow('tenant.created', ['created_at' => now()->subMinutes(3), 'metadata' => ['reason' => 'Alta']]);

    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        // In the order the database keeps the keys (jsonb: shorter first).
        ->where('entries.data.0.change', [
            ['field' => 'Área', 'before' => '—', 'after' => 'a1'],
            ['field' => 'Puesto', 'before' => 'Analista', 'after' => 'Gerente'],
        ])
        ->where('entries.data.0.context', [])
        ->where('entries.data.1.change', [['field' => 'Tipo de jornada', 'before' => 'Diurna', 'after' => 'Nocturna']])
        ->where('entries.data.1.context', [])
        ->where('entries.data.2.change', [['field' => 'Tipo de jornada', 'before' => '—', 'after' => 'Mixta']])
        ->where('entries.data.3.change', [])
        ->where('entries.data.3.context', ['Motivo' => 'Alta']));
});

it('never shows in the detail a key the logs keep out, even if an entry stored one, unless the trail names it on purpose', function (): void {
    auditRow('billing.payment_recorded', ['metadata' => [
        'answers' => ['q1' => 4],
        'comment' => 'Mi jefe me grita',
        'email' => 'ana@acme.mx',
        'amount_cents' => 50000,
        'changes' => ['note' => ['before' => 'a', 'after' => 'b'], 'job_title' => ['before' => 'A', 'after' => 'B']],
    ]]);

    $response = $this->actingAs(superadmin())->get(route('audit.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', ['Monto' => '$500.00'])
        ->where('entries.data.0.change', [['field' => 'Puesto', 'before' => 'A', 'after' => 'B']]));
    expect($response->getContent())->not->toContain('Mi jefe me grita')->not->toContain('ana@acme.mx');
});

it('writes an offline mark entry with its time basis, signer and flags in Spanish', function (): void {
    auditRow('attendance.offline_mark_accepted', ['metadata' => [
        'time_basis' => 'qr_window',
        'signer_type' => 'device_key',
        'interval_minutes' => 120,
        'review_flags' => ['wide_interval', 'no_position', 'zones_not_configured'],
    ]]);

    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', [
            'Base de la hora' => 'Ventana del QR',
            'Firmante' => 'Llave del teléfono',
            'Minutos del intervalo' => '120',
            'Banderas de revisión' => 'Intervalo ancho, Sin posición, Centro sin zonas',
        ]));
});

it('says who was impersonated and in which company on an impersonation entry', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Demo']);
    $owner = User::factory()->create(['name' => 'Dueña Acme', 'email' => 'duena@acme.mx']);
    $staff = User::factory()->create();
    auditRow('impersonation.started', ['tenant_id' => $tenant->id, 'actor_id' => $staff->id, 'impersonator_id' => $staff->id, 'subject_type' => $owner->getMorphClass(), 'subject_id' => (string) $owner->id, 'metadata' => ['tenant_id' => $tenant->id]]);

    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', ['Empresa' => 'Acme Demo', 'Persona suplantada' => 'Dueña Acme (duena@acme.mx)']));
});

it('shows a role of the company by the label its owner typed, never by its internal key, reading the labels once', function (): void {
    $tenant = Tenant::factory()->create();
    $by = User::factory()->create();
    $audit = app(App\Modules\Tenancy\Actions\CreateCompanyRole::class)->handle($tenant, 'Auditoría', ['view-dashboard'], null, $by);
    $other = app(App\Modules\Tenancy\Actions\CreateCompanyRole::class)->handle($tenant, 'Atención externa', ['view-dashboard'], null, $by);
    auditRow('tenant_invitation.sent', ['metadata' => ['role' => $audit->name]]);
    auditRow('tenant_member.role_changed', ['metadata' => ['from' => $audit->name, 'to' => $other->name, 'role' => 'viewer']]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs(superadmin())->get(route('audit.index'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data', fn ($entries): bool => collect($entries)->pluck('context')->contains(['Rol' => 'Auditoría'])
            && collect($entries)->pluck('context')->contains(['Desde' => 'Auditoría', 'Hasta' => 'Atención externa', 'Rol' => 'Consulta'])));
    $roleReads = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_starts_with((string) $log['query'], 'select "name", "label" from "roles"'));
    DB::disableQueryLog();

    expect($roleReads)->toHaveCount(1);
});
