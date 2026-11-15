<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\EmployeeController;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Notifications\PortalLinkEmail;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

mutates(EmployeeController::class);

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
});

function sendPortalLinkTo(string $employeeId): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.employees.portal-link', [test()->tenant, $employeeId]));
}

it('lets RH send a person the link of their portal, the same one they already have', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $token = resolve(PortalAccess::class)->token($this->ana->id);

    sendPortalLinkTo($this->ana->id)->assertRedirect()->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(PortalLinkEmail::class, function (PortalLinkEmail $notification, array $channels, AnonymousNotifiable $notifiable) use ($token): bool {
        $mail = $notification->toMail($notifiable);

        expect($notifiable->routes['mail'])->toBe('ana@acme.test')
            ->and($channels)->toBe(['mail'])
            ->and($mail->actionUrl)->toBe(route('portal.show', $token))
            ->and($mail->greeting)->toBe('Hola, Ana Ruiz')
            ->and(implode("\n", [...$mail->introLines, ...$mail->outroLines]))->not->toContain('tu ');

        return true;
    });
    expect(resolve(PortalAccess::class)->token($this->ana->id))->toBe($token);
});

it('logs that the link went out, for whom and never the link', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR);
    });

    sendPortalLinkTo($this->ana->id);

    $sent = array_values(array_filter($lines, fn (string $line): bool => str_starts_with($line, 'portal.link_sent')));
    expect($sent)->toHaveCount(1)
        ->and($sent[0])->toContain($this->ana->id)
        ->and(implode("\n", $lines))->not->toContain(resolve(PortalAccess::class)->token($this->ana->id));
});

it('tells RH why nothing was sent when the person has no email, and sends nothing', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $this->ana->forceFill(['email' => null])->save();

    sendPortalLinkTo($this->ana->id)->assertRedirect()->assertSessionHasErrors(['email' => 'No tiene correo registrado: agrégalo en su ficha para enviarle el enlace.']);

    Notification::assertNothingSent();
});

it('does not exist for a person who left, for a made-up id or for another company', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $gone = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'email' => 'gone@acme.test']);
    $stranger = Employee::factory()->create(['email' => 'otro@otra.test']);

    sendPortalLinkTo($gone->id)->assertConflict();
    sendPortalLinkTo((string) Str::uuid())->assertNotFound();
    sendPortalLinkTo($stranger->id)->assertNotFound();

    Notification::assertNothingSent();
});

it('is for whoever manages the organization, not for a viewer', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    sendPortalLinkTo($this->ana->id)->assertForbidden();

    Notification::assertNothingSent();
});

it('is throttled and named, like the other actions on a person', function (): void {
    $route = Illuminate\Support\Facades\Route::getRoutes()->getByName('tenant.employees.portal-link');

    expect($route?->gatherMiddleware())->toContain('throttle:20,1');
});
