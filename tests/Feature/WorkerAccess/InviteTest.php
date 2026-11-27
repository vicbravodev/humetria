<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Actions\IssueAccessLink;
use App\Modules\WorkerAccess\Contracts\WorkerInvitations;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(IssueAccessLink::class, CreateAccessLink::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    $this->rh = ownerOf($this->tenant);
    workIn($this->tenant);
    Context::add('user_id', $this->rh->id);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    Notification::fake();
});

/** The token at the end of a link's address. */
function tokenOf(string $url): string
{
    return basename((string) parse_url($url, PHP_URL_PATH));
}

it('emails the person a link RH issued, good once for 48 hours, and never gives it to RH', function (): void {
    $outcome = resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    expect($outcome)->toEqual(new InvitationOutcome(InvitationOutcome::SENT));
    $link = WorkerAccessLink::query()->sole();
    expect($link)->kind->value->toBe('issued')->issued_by->toBe($this->rh->id)->employee_id->toBe($this->ana->id)
        ->and($link->expires_at->toIso8601String())->toBe('2026-11-18T15:00:00+00:00');
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notice, array $channels, object $notifiable) use ($link): bool {
        $mail = $notice->toMail($notifiable);

        expect($notifiable->routes['mail'])->toBe('ana@acme.mx')
            ->and($mail->subject)->toBe('Entre a su portal de Acme')
            ->and($mail->greeting)->toBe('Hola, Ana López')
            ->and($mail->actionText)->toBe('Crear mi llave')
            ->and((string) $mail->actionUrl)->toStartWith(rtrim(config()->string('app.url'), '/').'/me/acme/link/')
            ->and(WorkerAccessLink::findPending(tokenOf((string) $mail->actionUrl))?->id)->toBe($link->id)
            ->and(implode(' ', $mail->outroLines))->toContain('una sola vez')->toContain('48 horas');

        return true;
    });
});

it('shows RH the link once for a person without email, and the audit trail says RH delivered it', function (): void {
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'email' => null]);

    $outcome = resolve(WorkerInvitations::class)->send($beto->id, $this->rh->id);

    expect($outcome->status)->toBe(InvitationOutcome::WITHOUT_EMAIL)
        ->and(WorkerAccessLink::findPending(tokenOf((string) $outcome->url))?->employee_id)->toBe($beto->id);
    Notification::assertNothingSent();
    $entry = AuditEntry::query()->where('event', 'worker_access.link_issued')->sole();
    expect($entry)->actor_id->toBe($this->rh->id)->metadata->toBe(['employee_id' => $beto->id, 'delivered_by_rh' => true]);
});

it('revokes every link of the person that still opened, RH\'s and the person\'s', function (): void {
    $issued = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $requested = WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    expect($issued->refresh()->revoked_at)->not->toBeNull()
        ->and($requested->refresh()->revoked_at)->not->toBeNull()
        ->and(WorkerAccessLink::query()->whereNull('revoked_at')->count())->toBe(1);
});

it('writes the issue to the audit trail and the log with ids only, never the link', function (): void {
    Log::spy();

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    $link = WorkerAccessLink::query()->sole();
    expect(AuditEntry::query()->where('event', 'worker_access.link_issued')->sole())
        ->subject_id->toBe($link->id)->metadata->toBe(['employee_id' => $this->ana->id, 'delivered_by_rh' => false]);
    Log::shouldHaveReceived('info')->with('worker_access.link_issued', ['link_id' => $link->id, 'employee_id' => $this->ana->id, 'delivered_by_rh' => false]);
});

it('issues nothing for a person who left or is not the company\'s', function (string $case): void {
    $id = $case === 'left'
        ? EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id])->id
        : EmployeeFactory::new()->create(['tenant_id' => Tenant::factory()->create()->id])->id;
    workIn($this->tenant);

    expect(resolve(WorkerInvitations::class)->send($id, $this->rh->id))->toEqual(new InvitationOutcome(InvitationOutcome::UNAVAILABLE))
        ->and(WorkerAccessLink::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with(['left', 'of another company']);
