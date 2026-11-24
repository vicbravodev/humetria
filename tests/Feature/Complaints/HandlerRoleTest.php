<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\RequireRoutePermission;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

mutates(RequireRoutePermission::class);

it('invites a member as complaints handler, who is told what the role is for', function (): void {
    Notification::fake();
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, TenantRole::Owner))
        ->post(route('tenant.members.invitations.store', $tenant), ['name' => 'Abel Abogado', 'email' => 'abel@bufete.mx', 'role' => 'complaints'])
        ->assertRedirect();

    expect(TenantInvitation::query()->where('email', 'abel@bufete.mx')->sole()->role?->name)->toBe(TenantRole::Complaints->value)
        ->and(TenantRole::Complaints->label())->toBe('Atención de quejas')
        ->and(TenantRole::Complaints->invitedAs('Acme'))->toBe('Te invitaron a atender el canal de quejas de Acme');
});

it('gives the complaints role the ability to handle complaints and nothing else', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, TenantRole::Complaints));
    workIn($tenant);

    expect(Gate::allows('handle-complaints'))->toBeTrue()
        ->and(Gate::allows('manage-complaint-channel'))->toBeFalse()
        ->and(Gate::allows('view-telework'))->toBeFalse()
        ->and(Gate::allows('view-seating'))->toBeFalse()
        ->and(Gate::allows('manage-organization'))->toBeFalse();
});

it('limits restricted roles to their own tools', function (TenantRole $role, array $open, array $closed): void {
    $tenant = Tenant::factory()->create();
    $person = memberOf($tenant, $role);

    foreach ($open as $name) {
        $this->actingAs($person)->get(route($name, $tenant))->assertOk();
    }

    foreach ($closed as $name) {
        $this->actingAs($person)->get(route($name, $tenant))->assertForbidden();
    }
})->with([
    'committee: NOM-037, Ley Silla and the channel' => [TenantRole::Committee, ['tenant.telework.index', 'tenant.seating.index', 'tenant.complaints.index'], ['tenant.campaigns.index']],
    'complaints: only the channel' => [TenantRole::Complaints, ['tenant.complaints.index'], ['tenant.telework.index', 'tenant.seating.index', 'tenant.campaigns.index']],
    'hr: everything' => [TenantRole::Hr, ['tenant.telework.index', 'tenant.seating.index', 'tenant.complaints.index', 'tenant.campaigns.index'], []],
]);

it('sends the panel visit of a complaints handler to the channel instead of answering 403', function (): void {
    $tenant = Tenant::factory()->create();
    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class])->get('admin/{tenant:slug}/complaints-start', fn () => 'ok')->name('tenant.complaints.index');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs(memberOf($tenant, TenantRole::Complaints))
        ->get(route('tenant.dashboard', $tenant))
        ->assertRedirect(route('tenant.complaints.index', $tenant));
});
