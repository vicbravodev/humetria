<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\LimitCommitteeToTelework;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyMembers;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

mutates(LimitCommitteeToTelework::class, CompanyMembers::class);

it('invites a member as committee', function (): void {
    Notification::fake();
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, TenantRole::Owner))
        ->post(route('tenant.members.invitations.store', $tenant), ['name' => 'Cora Comisión', 'email' => 'cora@acme.mx', 'role' => 'committee'])
        ->assertRedirect();

    expect(TenantInvitation::query()->where('email', 'cora@acme.mx')->sole()->role)->toBe(TenantRole::Committee)
        ->and(TenantRole::Committee->label())->toBe('Comisión de seguridad e higiene')
        ->and(TenantRole::Committee->invitedAs('Acme'))->toBe('Te invitaron a la Comisión de seguridad e higiene de Acme');
});

it('gives each role the telework permissions of the spec', function (TenantRole $role, bool $view, bool $manage, bool $review): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-telework'))->toBe($view)
        ->and(Gate::allows('manage-telework'))->toBe($manage)
        ->and(Gate::allows('review-workplaces'))->toBe($review);
})->with([
    'owner' => [TenantRole::Owner, true, true, true],
    'hr' => [TenantRole::Hr, true, true, true],
    'viewer' => [TenantRole::Viewer, true, false, false],
    'committee' => [TenantRole::Committee, true, false, true],
]);

it('limits a committee member to the telework routes', function (): void {
    $tenant = Tenant::factory()->create();
    $committee = memberOf($tenant, TenantRole::Committee);
    $owner = memberOf($tenant, TenantRole::Owner);

    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class, LimitCommitteeToTelework::class])
        ->get('admin/{tenant:slug}/telework-probe', fn () => 'ok')->name('tenant.telework.probe');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs($committee)->get(route('tenant.telework.probe', $tenant))->assertOk();
    $this->actingAs($owner)->get(route('tenant.telework.probe', $tenant))->assertOk();

    foreach (['tenant.employees.index', 'tenant.campaigns.index', 'tenant.members.index', 'tenant.work-centers.index'] as $name) {
        $this->actingAs($committee)->get(route($name, $tenant))->assertForbidden();
    }

    $this->actingAs($committee)->post(route('tenant.members.invitations.store', $tenant), ['name' => 'X', 'email' => 'x@acme.mx', 'role' => 'hr'])->assertForbidden();
    $this->actingAs($owner)->get(route('tenant.employees.index', $tenant))->assertOk();
});

it('limits a committee member on the results routes', function (string $name): void {
    $campaign = Campaign::factory()->create();
    $tenant = tenantOf($campaign->tenant_id);
    $committee = memberOf($tenant, TenantRole::Committee);

    $this->actingAs($committee)->get(route($name, [$tenant, $campaign]))->assertForbidden();
})->with(['tenant.campaigns.results', 'tenant.campaigns.screenings', 'tenant.campaigns.report']);

it('lets a committee member reach the account settings and log out', function (): void {
    $tenant = Tenant::factory()->create();
    $committee = memberOf($tenant, TenantRole::Committee);

    $this->actingAs($committee)->get(route('profile.edit'))->assertOk();
    $this->actingAs($committee)->get(route('appearance.edit'))->assertOk();
    $this->actingAs($committee)->post(route('logout'))->assertRedirect();
});

it('lists the members of the current company only', function (): void {
    $tenant = Tenant::factory()->create();
    $ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@acme.mx']);
    $cora = User::factory()->create(['name' => 'Cora', 'email' => 'cora@acme.mx']);
    TenantMembership::factory()->for($tenant)->for($ana)->hr()->create();
    TenantMembership::factory()->for($tenant)->for($cora)->role(TenantRole::Committee)->create();
    $other = Tenant::factory()->create();
    $foreign = memberOf($other, TenantRole::Hr);
    workIn($tenant);

    $members = resolve(Members::class);

    expect($members->all())->toEqual([
        new MemberSummary($ana->id, 'Ana', 'ana@acme.mx', TenantRole::Hr),
        new MemberSummary($cora->id, 'Cora', 'cora@acme.mx', TenantRole::Committee),
    ])
        ->and($members->find($cora->id))->toEqual(new MemberSummary($cora->id, 'Cora', 'cora@acme.mx', TenantRole::Committee))
        ->and($members->find($foreign->id))->toBeNull()
        ->and($members->find(0))->toBeNull();
});

it('lists as reviewers the members whose role can review workplaces, by name', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create(['name' => 'Beto']);
    $committee = User::factory()->create(['name' => 'Cora']);
    $hr = User::factory()->create(['name' => 'Ana']);
    $owner = User::factory()->create(['name' => 'Zoe']);
    TenantMembership::factory()->for($tenant)->for($viewer)->role(TenantRole::Viewer)->create();
    TenantMembership::factory()->for($tenant)->for($owner)->role(TenantRole::Owner)->create();
    TenantMembership::factory()->for($tenant)->for($committee)->role(TenantRole::Committee)->create();
    TenantMembership::factory()->for($tenant)->for($hr)->hr()->create();
    memberOf(Tenant::factory()->create(), TenantRole::Hr);
    workIn($tenant);

    expect(array_map(fn (MemberSummary $member): int => $member->userId, resolve(Members::class)->reviewers()))
        ->toBe([$hr->id, $committee->id, $owner->id]);
});

it('reads the people of the company once while it serves them', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Committee);
    workIn($tenant);
    $members = resolve(Members::class);
    Illuminate\Support\Facades\DB::enableQueryLog();

    $members->all();
    $members->all();

    expect(collect(Illuminate\Support\Facades\DB::getQueryLog())->filter(fn (array $log): bool => str_contains((string) $log['query'], 'from "users"')))->toHaveCount(1);
});
