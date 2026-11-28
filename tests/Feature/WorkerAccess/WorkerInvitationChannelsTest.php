<?php

declare(strict_types=1);

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Events\WhatsAppMessageFailed;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerInvitations;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Listeners\FallBackToMail;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use App\Modules\WorkerAccess\Support\WorkerInvitationLinks;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

mutates(FallBackToMail::class, WorkerInvitationLinks::class);

/*
 * The invitation to the portal goes through the company's channels like a survey invitation: WhatsApp (`worker_invite_v1`) when
 * the company puts it first, behind the switch Victor turns on once Meta approves the template; the mail takes over when WhatsApp
 * fails, without a second link.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    Http::preventStrayRequests();
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'whatsapp.templates.worker_invite_v1' => true,
        'messaging.recipient_keys' => [1 => base64_encode('a-key')], 'billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 100]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']));
    $this->rh = ownerOf($this->tenant);
    workIn($this->tenant);
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx', 'mobile' => '+528186875700']);
    $this->gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $this->gateway);
});

/** The WhatsApp messages the company holds or used this month. */
function portalWhatsAppUse(Tenant $tenant): array
{
    $counter = UsageCounter::query()->where('tenant_id', $tenant->id)->where('meter', 'whatsapp')->first();

    return ['used' => (int) ($counter->used ?? 0), 'reserved' => (int) ($counter->reserved ?? 0)];
}

function portalFailure(WorkerAccessLink $link, FailureCause $cause = FailureCause::NoWhatsappAccount, string $type = 'worker_access_link'): WhatsAppMessageFailed
{
    return new WhatsAppMessageFailed((string) Str::uuid(), $link->tenant_id, CarbonImmutable::now(), new InvitationReference($type, $link->id, 'worker_access', $link->id), $cause);
}

it('sends the portal invitation by WhatsApp when the company can', function (): void {
    $outcome = resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    $link = WorkerAccessLink::query()->sole();
    expect($outcome)->toEqual(new InvitationOutcome(InvitationOutcome::SENT))
        ->and($this->gateway->sent())->toHaveCount(1);
    $message = $this->gateway->sent()[0];
    expect($message->to)->toBe('+528186875700')
        ->and($message->template)->toBe('worker_invite_v1')
        ->and($message->bodyParameters)->toBe(['Acme'])
        ->and(WorkerAccessLink::findPending($message->buttonSuffix)?->id)->toBe($link->id)
        // WhatsApp first: the mail does not go too, and the message took its place from a hold of the company's quota.
        ->and(Delivery::query()->where('invitation_id', $link->id)->pluck('channel')->all())->toBe(['whatsapp'])
        ->and(portalWhatsAppUse($this->tenant))->toBe(['used' => 1, 'reserved' => 0]);
});

it('holds the message from the quota, and sends by mail when the quota is used up', function (): void {
    config(['billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 0]);
    Notification::fake();

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    Notification::assertSentOnDemand(WorkerInvitation::class, fn (WorkerInvitation $notice, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail'] && $notifiable->routes === ['mail' => 'ana@acme.mx']);
});

it('sends the portal invitation by mail while the template is off, even when WhatsApp comes first', function (): void {
    config(['whatsapp.templates.worker_invite_v1' => false]);
    Notification::fake();

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    Notification::assertSentOnDemand(WorkerInvitation::class, fn (WorkerInvitation $notice, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail'] && $notifiable->routes === ['mail' => 'ana@acme.mx']);
    expect($this->gateway->sent())->toBe([])->and(portalWhatsAppUse($this->tenant))->toBe(['used' => 0, 'reserved' => 0]);
});

it('still mails the invitation when the company has no channel that reaches the person', function (): void {
    App\Modules\Tenancy\Models\TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['enabled' => false]);
    Notification::fake();

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);

    Notification::assertSentOnDemand(WorkerInvitation::class, fn (WorkerInvitation $notice, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail'] && $notifiable->routes === ['mail' => 'ana@acme.mx']);
});

it('falls back to mail when WhatsApp rejects the invitation', function (): void {
    Log::spy();
    Notification::fake();

    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);
    $link = WorkerAccessLink::query()->sole();
    event(portalFailure($link));

    // The same link, which never reached the person: no second link that opens.
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notice, array $channels, AnonymousNotifiable $notifiable) use ($link): bool {
        if ($channels !== ['mail']) {
            return false;
        }

        expect($notifiable->routes)->toBe(['mail' => 'ana@acme.mx'])
            ->and(WorkerAccessLink::findPending(basename((string) parse_url((string) $notice->toMail($notifiable)->actionUrl, PHP_URL_PATH)))?->id)->toBe($link->id);

        return true;
    });
    expect(WorkerAccessLink::query()->count())->toBe(1);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'worker_access.fallback_sent' && $context === ['link_id' => $link->id, 'cause' => 'no_whatsapp_account'])->once();
});

it('does not mail again a link the mail already carried, or one that no longer opens', function (Closure $prepare): void {
    Notification::fake();
    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);
    $link = WorkerAccessLink::query()->sole();
    $prepare($this, $link);
    Notification::fake();

    event(portalFailure($link));

    Notification::assertNothingSent();
})->with([
    'used' => [fn ($test, WorkerAccessLink $link) => $link->update(['used_at' => now()])],
    'expired' => [fn ($test) => $test->travel(49)->hours()],
    'the person left' => [fn ($test) => $test->ana->update(['deactivated_at' => now()])],
    'without email' => [fn ($test) => $test->ana->update(['email' => null])],
]);

it('does not mail again in the demo, which sent the link by mail and WhatsApp at once', function (): void {
    $demo = Tenant::factory()->demo()->create(['trade_name' => 'Demo']);
    workIn($demo);
    TenantChannelFactory::new()->for($demo)->create(['channel' => 'mail', 'preference' => 1]);
    TenantChannelFactory::new()->for($demo)->whatsAppEnabled()->create(['preference' => 2]);
    $guest = EmployeeFactory::new()->create(['tenant_id' => $demo->id, 'email' => 'publico@example.test', 'mobile' => '+528186875701']);
    Notification::fake();

    resolve(WorkerInvitations::class)->send($guest->id, ownerOf($demo)->id);
    Notification::assertSentOnDemand(WorkerInvitation::class, fn (WorkerInvitation $notice, array $channels): bool => $channels === ['mail', 'whatsapp']);
    $link = WorkerAccessLink::query()->sole();
    Notification::fake();

    event(portalFailure($link));

    Notification::assertNothingSent();
});

it('does nothing for a cause the mail does not take over from, or an invitation of another module', function (FailureCause $cause, string $type): void {
    Notification::fake();
    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);
    $link = WorkerAccessLink::query()->sole();
    Notification::fake();

    event(portalFailure($link, $cause, $type));

    Notification::assertNothingSent();
})->with([
    'paused' => [FailureCause::Paused, 'worker_access_link'],
    'another module' => [FailureCause::NoWhatsappAccount, InvitationReference::CAMPAIGN_PARTICIPANT],
]);

it('gives the WhatsApp job nothing to send once the link no longer opens, the person left or has no mobile', function (): void {
    $links = resolve(WorkerInvitationLinks::class);
    resolve(WorkerInvitations::class)->send($this->ana->id, $this->rh->id);
    $link = WorkerAccessLink::query()->sole();

    expect($links->whatsApp($link->id)?->mobile)->toBe('+528186875700')
        ->and($links->whatsApp((string) Str::uuid()))->toBeNull();

    App\Modules\Organization\Models\Employee::query()->whereKey($this->ana->id)->update(['mobile' => null]);
    expect($links->whatsApp($link->id))->toBeNull();

    App\Modules\Organization\Models\Employee::query()->whereKey($this->ana->id)->update(['mobile' => '+528186875700', 'deactivated_at' => now()]);
    expect($links->whatsApp($link->id))->toBeNull();

    App\Modules\Organization\Models\Employee::query()->whereKey($this->ana->id)->update(['deactivated_at' => null]);
    $link->forceFill(['used_at' => now()])->save();
    expect($links->whatsApp($link->id))->toBeNull();
});
