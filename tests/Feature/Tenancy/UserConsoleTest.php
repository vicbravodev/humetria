<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\UserPages;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(UserPages::class);

/** A user with two-factor on, as Fortify leaves it once confirmed. */
function userWithTwoFactor(array $attributes = []): User
{
    return User::factory()->create([
        'two_factor_secret' => 'secret',
        'two_factor_recovery_codes' => 'codes',
        'two_factor_confirmed_at' => now(),
        ...$attributes,
    ]);
}

it('lives at /admin/platform-users', function (): void {
    expect(route('platform-users.index', absolute: false))->toBe('/admin/platform-users');
});

it('sends guests to the login', function (): void {
    $this->get(route('platform-users.index'))->assertRedirect(route('login'));
});

it('answers 403 to support and to anyone without a platform role, on every route', function (User $user): void {
    $target = userWithTwoFactor();
    $this->actingAs($user);

    $this->get(route('platform-users.index'))->assertForbidden();
    $this->post(route('platform-users.password-reset', $target))->assertForbidden();
    $this->delete(route('platform-users.two-factor.destroy', $target))->assertForbidden();
    $this->post(route('platform-users.support.store', $target))->assertForbidden();
    $this->delete(route('platform-users.support.destroy', $target))->assertForbidden();

    expect($target->fresh()->two_factor_secret)->toBe('secret');
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('lists the users with their role, two-factor, verification and companies, newest first', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $other = Tenant::factory()->create(['trade_name' => 'Beta']);
    $ana = memberOf($tenant, TenantRole::Owner);
    $ana->forceFill(['name' => 'Ana López', 'email' => 'ana@acme.mx', 'two_factor_confirmed_at' => now(), 'created_at' => now()->addDay()])->save();
    setPermissionsTeamId($other->id);
    $ana->assignRole(TenantRole::Hr->value);
    setPermissionsTeamId(null);
    $help = support();
    $help->forceFill(['name' => 'Soporte Uno', 'email_verified_at' => null])->save();

    $this->actingAs(superadmin())->get(route('platform-users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/users')
            ->where('users.total', 3)
            ->where('users.data.0.name', 'Ana López')
            ->where('users.data.0.platformRole', null)
            ->where('users.data.0.twoFactor', true)
            ->where('users.data.0.verified', true)
            ->where('users.data.0.companies', 'Acme (owner), Beta (hr)')
            ->where('users.data.1.platformRole', 'superadmin')
            ->where('users.data.2.platformRole', 'support')
            ->where('users.data.2.verified', false)
            ->where('users.data.2.companies', ''));
});

it('never sends the secret of a user to the page', function (): void {
    userWithTwoFactor(['name' => 'Ana López']);

    $json = $this->actingAs(superadmin())->get(route('platform-users.index'))->getContent();

    expect($json)->not->toContain('secret')->not->toContain('codes')->not->toContain('remember_token');
});

it('searches by name and email, ignoring accents, case and the order of the words', function (string $search, array $names): void {
    User::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@acme.mx']);
    User::factory()->create(['name' => 'Rosa Medina', 'email' => 'rosa@beta.mx']);

    $this->actingAs(superadmin())->get(route('platform-users.index', ['q' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', $search)
            ->where('users.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === $names));
})->with([
    'accents' => ['perez', ['Juan Pérez']],
    'words in any order' => ['JUAN perez', ['Juan Pérez']],
    'email' => ['@beta', ['Rosa Medina']],
    'nothing' => ['zzz', []],
]);

it('pages by 25', function (): void {
    User::factory()->count(26)->create();

    $this->actingAs(superadmin())->get(route('platform-users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users.per_page', 25)->has('users.data', 25)->where('users.total', 27));
});

it('sends a password reset link to a user, and records it without the token', function (): void {
    Notification::fake();
    Log::spy();
    $staff = superadmin();
    $target = User::factory()->create();

    $this->actingAs($staff)->post(route('platform-users.password-reset', $target))
        ->assertRedirect()->assertInertiaFlash('toast.type', 'success');

    Notification::assertSentTo($target, ResetPasswordNotification::class);
    $entry = AuditEntry::query()->where('event', 'user.password_reset_sent')->sole();
    expect($entry->subject_id)->toBe((string) $target->id)
        ->and($entry->metadata)->toBe([]);
    Log::shouldHaveReceived('info')->once()->with('user.password_reset_sent', ['user_id' => $staff->id, 'target_user_id' => $target->id]);
});

it('does not put the staff member\'s own device in the reset email: the request was not the person\'s', function (): void {
    Notification::fake();
    $target = User::factory()->create();

    $this->actingAs(superadmin())->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36')
        ->post(route('platform-users.password-reset', $target));

    Notification::assertSentTo($target, ResetPasswordNotification::class, fn (ResetPasswordNotification $notice): bool => $notice->origin === null);
});

it('says so, and records nothing, when a link went out a moment ago', function (): void {
    Notification::fake();
    $staff = superadmin();
    $target = User::factory()->create();

    $this->actingAs($staff)->post(route('platform-users.password-reset', $target));
    $this->post(route('platform-users.password-reset', $target))
        ->assertRedirect()->assertSessionHasErrors('user');

    Notification::assertSentToTimes($target, ResetPasswordNotification::class, 1);
    expect(AuditEntry::query()->where('event', 'user.password_reset_sent')->count())->toBe(1);
});

it('removes the two-factor of a user and records it', function (): void {
    Log::spy();
    $staff = superadmin();
    $target = userWithTwoFactor();

    $this->actingAs($staff)->delete(route('platform-users.two-factor.destroy', $target))->assertRedirect();

    expect($target->fresh())
        ->two_factor_secret->toBeNull()
        ->two_factor_recovery_codes->toBeNull()
        ->two_factor_confirmed_at->toBeNull();
    expect(AuditEntry::query()->where('event', 'user.two_factor_removed')->sole()->subject_id)->toBe((string) $target->id);
    Log::shouldHaveReceived('info')->once()->with('user.two_factor_removed', ['user_id' => $staff->id, 'target_user_id' => $target->id]);
});

it('does not remove the two-factor of a superadmin, one\'s own or another\'s', function (bool $self): void {
    $staff = userWithTwoFactor();
    setPermissionsTeamId(null);
    $staff->assignRole(User::SUPERADMIN);
    $other = userWithTwoFactor();
    $other->assignRole(User::SUPERADMIN);

    $this->actingAs($staff)->delete(route('platform-users.two-factor.destroy', $self ? $staff : $other))->assertForbidden();

    expect(($self ? $staff : $other)->fresh()->two_factor_secret)->toBe('secret')
        ->and(AuditEntry::query()->where('event', 'user.two_factor_removed')->count())->toBe(0);
})->with(['one\'s own' => true, 'another superadmin' => false]);

it('gives and takes away the support role, each recorded', function (): void {
    $staff = superadmin();
    $target = User::factory()->create();
    $this->actingAs($staff);

    $this->post(route('platform-users.support.store', $target))->assertRedirect();
    expect($target->fresh()->isSupport())->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'user.support_granted')->sole()->subject_id)->toBe((string) $target->id);

    $this->delete(route('platform-users.support.destroy', $target))->assertRedirect();
    expect($target->fresh()->isSupport())->toBeFalse()
        ->and(AuditEntry::query()->where('event', 'user.support_revoked')->sole()->subject_id)->toBe((string) $target->id);
});

it('records a support role once, however many times it is given', function (): void {
    $this->actingAs(superadmin());
    $target = User::factory()->create();

    $this->post(route('platform-users.support.store', $target));
    $this->post(route('platform-users.support.store', $target));
    $this->delete(route('platform-users.support.destroy', $target));
    $this->delete(route('platform-users.support.destroy', $target));

    expect(AuditEntry::query()->where('event', 'user.support_granted')->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'user.support_revoked')->count())->toBe(1);
});

it('never gives support to a superadmin, nor takes a superadmin\'s own role away', function (): void {
    $staff = superadmin();
    $this->actingAs($staff);

    $this->post(route('platform-users.support.store', $staff))->assertForbidden();
    $this->delete(route('platform-users.support.destroy', $staff))->assertForbidden();

    expect($staff->fresh()->isSuperadmin())->toBeTrue()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('answers 404 for a user that does not exist', function (): void {
    $this->actingAs(superadmin())->post(route('platform-users.password-reset', 999999))->assertNotFound();
});

it('is forbidden while impersonating', function (): void {
    expect(ForbidWhileImpersonating::guards('platform-users.index'))->toBeTrue()
        ->and(ForbidWhileImpersonating::guards('platform-users.two-factor.destroy'))->toBeTrue();
});
