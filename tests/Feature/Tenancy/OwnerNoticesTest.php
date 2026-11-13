<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Notifications\CompanyReactivated;
use App\Modules\Tenancy\Notifications\CompanyRestricted;
use App\Modules\Tenancy\Notifications\ToolsChanged;
use App\Modules\Tenancy\Support\OwnerNotices;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(OwnerNotices::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-12 18:00 UTC');
    config(['app.support_email' => 'soporte@humetria.test']);
    Notification::fake();
});

/** A company with an owner, whose plan has only `$modules`. */
function ownedPlanned(array $modules = ['nom035', 'attendance', 'vacations']): array
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $owner = memberOf($tenant, TenantRole::Owner);
    resolve(CompanyModules::class)->syncPlan($tenant->id, $modules, CarbonImmutable::parse('2026-11-12'));
    Notification::fake();

    return [$tenant, $owner];
}

function mailOf(Illuminate\Notifications\Notification $notification): string
{
    return (string) $notification->toMail(new AnonymousNotifiable)->render();
}

it('tells the owner when the company goes read-only, suspended or archived, with the reason and where to write', function (TenantRestriction $restriction): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $owner = memberOf($tenant, TenantRole::Owner);

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => $restriction->value, 'reason' => 'Falta de pago de octubre'])->assertRedirect();

    Notification::assertSentTo($owner, CompanyRestricted::class, function (CompanyRestricted $notice) use ($restriction): bool {
        $html = mailOf($notice);

        return str_contains($html, 'Acme')
            && str_contains($html, 'Falta de pago de octubre')
            && str_contains($html, 'soporte@humetria.test')
            && str_contains($html, $restriction === TenantRestriction::ReadOnly ? 'solo lectura' : ($restriction === TenantRestriction::Suspended ? 'suspendida' : 'archivada'));
    });
})->with([TenantRestriction::ReadOnly, TenantRestriction::Suspended, TenantRestriction::Archived]);

it('speaks to the owner as tú and never names an amount', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $owner = memberOf($tenant, TenantRole::Owner);

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])->assertRedirect();

    Notification::assertSentTo($owner, CompanyRestricted::class, function (CompanyRestricted $notice): bool {
        $html = mailOf($notice);

        return str_contains($html, 'escríbenos a') && ! preg_match('/\$|MXN|pesos|saldo|adeudo/i', $html);
    });
});

it('tells the owner when the company is reactivated', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme', ...suspension()]);
    $owner = memberOf($tenant, TenantRole::Owner);

    $this->actingAs(superadmin())->post(route('companies.reactivate', $tenant), ['reason' => 'Pagó'])->assertRedirect();

    Notification::assertSentTo($owner, CompanyReactivated::class, fn (CompanyReactivated $notice): bool => str_contains(mailOf($notice), 'Acme') && ! str_contains(mailOf($notice), 'Pagó'));
});

it('applies the suspension and records that nobody was told when the company has no owner', function (): void {
    Log::spy();
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])->assertRedirect();

    expect($tenant->fresh()->restriction)->toBe(TenantRestriction::Suspended);
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->with('tenant.owner_not_notified', ['tenant_id' => $tenant->id, 'event' => 'tenant.suspended', 'why' => 'no_owner']);
});

it('applies it and records it when the owner has not verified the email', function (): void {
    Log::spy();
    $tenant = Tenant::factory()->create();
    $owner = memberOf($tenant, TenantRole::Owner);
    $owner->forceFill(['email_verified_at' => null])->save();

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'read_only', 'reason' => 'Falta de pago'])->assertRedirect();

    expect($tenant->fresh()->restriction)->toBe(TenantRestriction::ReadOnly);
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->with('tenant.owner_not_notified', ['tenant_id' => $tenant->id, 'event' => 'tenant.set_read_only', 'why' => 'unverified_email']);
});

it('sends no notice when the restriction fails to be saved', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner);

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => ''])->assertSessionHasErrors('reason');

    Notification::assertNothingSent();
});

it('sends the notice of a restriction only after it is committed', function (): void {
    expect((new CompanyRestricted(new App\Modules\Tenancy\Data\CompanySummary('t', 'Acme', 'acme', false), TenantRestriction::Suspended, 'x', null))->afterCommit)->toBeTrue()
        ->and((new CompanyReactivated(new App\Modules\Tenancy\Data\CompanySummary('t', 'Acme', 'acme', false), null))->afterCommit)->toBeTrue();
});

it('tells the owner once which tools are gone and from when, when a plan takes several away', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035', 'attendance', 'vacations']);

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertSentToTimes($owner, ToolsChanged::class, 1);
    Notification::assertSentTo($owner, ToolsChanged::class, function (ToolsChanged $notice): bool {
        $html = mailOf($notice);

        return str_contains($html, 'Asistencia') && str_contains($html, 'Vacaciones') && str_contains($html, '1 dic 2026') && ! str_contains($html, 'NOM-035');
    });
});

it('tells the owner what a plan turns on, when it only adds', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035']);

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'seating'], CarbonImmutable::parse('2026-11-12'));

    Notification::assertSentToTimes($owner, ToolsChanged::class, 1);
    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains(mailOf($notice), 'Ley Silla'));
});

it('says both what comes and what goes in the same notice', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035', 'attendance']);

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'seating'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertSentToTimes($owner, ToolsChanged::class, 1);
    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains(mailOf($notice), 'Ley Silla') && str_contains(mailOf($notice), 'Asistencia'));
});

it('sends nothing when the plan changes nothing', function (): void {
    [$tenant] = ownedPlanned(['nom035', 'attendance']);

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'attendance'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertNothingSent();
});

it('tells the owner when the superadmin takes a tool away by hand, from today', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035', 'attendance']);

    $this->actingAs(superadmin())->post(route('companies.modules.revoke', $tenant), ['module' => 'attendance', 'reason' => 'Acuerdo'])->assertSessionHasNoErrors();

    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains(mailOf($notice), 'Asistencia') && str_contains(mailOf($notice), 'desde hoy') && ! str_contains(mailOf($notice), '12 nov 2026') && ! str_contains(mailOf($notice), 'Acuerdo'));
});

it('tells the owner when the superadmin gives a tool by hand', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.grant', $tenant), ['module' => 'seating', 'reason' => 'Piloto'])->assertSessionHasNoErrors();

    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains(mailOf($notice), 'Ley Silla'));
});

it('does not tell the owner of a tool change the superadmin was refused', function (): void {
    [$tenant] = ownedPlanned(['nom035']);

    $this->actingAs(superadmin())->post(route('companies.modules.revoke', $tenant), ['module' => 'seating', 'reason' => 'x'])->assertSessionHasErrors('module');

    Notification::assertNothingSent();
});

it('records that nobody was told of a tool change in a company without an owner', function (): void {
    Log::spy();
    $tenant = Tenant::factory()->create();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->with('tenant.owner_not_notified', ['tenant_id' => $tenant->id, 'event' => 'tenant.modules_changed', 'why' => 'no_owner']);
});

it('says a tool that ends today is unavailable from today, and one that ends later, on its day', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035', 'attendance']);

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-11-12'));
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'attendance'], CarbonImmutable::parse('2026-11-12'));
    Notification::fake();
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains(mailOf($notice), 'el 1 dic 2026') && ! str_contains(mailOf($notice), 'desde hoy'));
});

it('greets the owner by name in each notice', function (): void {
    [$tenant, $owner] = ownedPlanned(['nom035', 'attendance']);
    $owner->forceFill(['name' => 'Ana López'])->save();

    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035'], CarbonImmutable::parse('2026-12-01'));

    Notification::assertSentTo($owner, ToolsChanged::class, fn (ToolsChanged $notice): bool => str_contains((string) $notice->toMail($owner)->render(), 'Hola, Ana López:'));
});
