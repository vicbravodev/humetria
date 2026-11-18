<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\ResendInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Messaging\Contracts\DeliverySummary;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * CUJ 5: an invitation goes out by mail, Resend says it bounced for good, and that is all: the message is bounced, the mail gauge
 * counts it as not delivered, nothing is written to WhatsApp (mail has no fallback) and RH can resend.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'services.resend.webhook_secrets' => ['whsec_'.base64_encode('the-secret')], 'services.resend.webhook_tolerance_seconds' => 300,
        'billing.versions.v1.plans.cumplimiento.quotas.whatsapp' => 100]);
    $this->gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $this->gateway);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    // Mail first, WhatsApp on and second: the person has a phone too, and still nothing goes to it when the mail bounces.
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();

    staffOf($this->tenant, $plant, 20)->each(fn ($employee) => $employee->update(['email' => Str::slug((string) $employee->id).'@example.test']));
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $plant->id]);
    $this->campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    // The queue is sync: the batch of invitations runs as the launch commits, and every invitation leaves by mail.
    $this->actingAs($this->hr)->post(route('tenant.campaigns.launch', [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();
});

function bounceFor(string $deliveryId, string $type = 'email.bounced', array $data = []): TestResponse
{
    $body = json_encode(['type' => $type, 'created_at' => '2026-11-02T17:00:00.000Z', 'data' => ['email_id' => 'resend-'.$deliveryId, 'tags' => ['delivery_id' => $deliveryId], ...$data]], JSON_THROW_ON_ERROR);
    $id = 'msg_'.Str::random(10);
    $timestamp = CarbonImmutable::now()->getTimestamp();
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", 'the-secret', true));

    return test()->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SVIX_ID' => $id, 'HTTP_SVIX_TIMESTAMP' => (string) $timestamp, 'HTTP_SVIX_SIGNATURE' => $signature], $body);
}

it('leaves every invitation as a followed mail message and none as a WhatsApp one', function (): void {
    expect(Delivery::query()->where('source_id', $this->campaign->id)->where('channel', 'mail')->count())->toBe(20)
        ->and(Delivery::query()->where('channel', 'whatsapp')->count())->toBe(0);
});

it('records the bounce, counts it as not delivered in the mail gauge, writes nothing to WhatsApp and lets RH resend', function (): void {
    $mails = Delivery::query()->where('source_id', $this->campaign->id)->where('channel', 'mail')->orderBy('id')->get();
    // Fifteen arrived; five bounced for good.
    foreach ($mails->take(15) as $delivery) {
        resolve(RecordDeliveryEvent::class)->handle($delivery, DeliveryStatus::Delivered, CarbonImmutable::now());
    }
    $bounced = $mails->slice(15)->values();

    foreach ($bounced as $delivery) {
        bounceFor($delivery->id, data: ['bounce' => ['type' => 'Permanent']])->assertOk();
    }

    foreach ($bounced as $delivery) {
        expect($delivery->refresh()->status)->toBe(DeliveryStatus::Bounced)
            ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'bounced')->exists())->toBeTrue();
    }
    resolve(Companies::class)->enter($this->tenant->id);
    $tally = collect(resolve(DeliverySummary::class)->forSource('campaign', $this->campaign->id))->firstWhere('channel', 'mail');
    expect($tally?->total)->toBe(20)->and($tally->delivered)->toBe(15)->and($tally->undelivered)->toBe(5)->and($tally->undeliveredBelowMinimum)->toBeFalse()
        ->and(Delivery::query()->where('channel', 'whatsapp')->count())->toBe(0)
        ->and($this->gateway->sent())->toBe([]);

    // RH resends to the person whose mail bounced: a new message, by mail again.
    $participant = Participant::query()->findOrFail($bounced[0]->invitation_id);
    $sent = resolve(ResendInvitations::class)->handle($this->campaign, [$participant->employee_id]);

    expect($sent)->toBe(1)
        ->and(Delivery::query()->where('invitation_id', $participant->id)->where('channel', 'mail')->count())->toBe(2)
        ->and(Delivery::query()->where('channel', 'whatsapp')->count())->toBe(0)
        ->and($this->gateway->sent())->toBe([]);
});

it('does not take a temporary bounce for a failure', function (): void {
    $delivery = Delivery::query()->where('source_id', $this->campaign->id)->where('channel', 'mail')->firstOrFail();

    bounceFor($delivery->id, data: ['bounce' => ['type' => 'Temporary']])->assertOk();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delayed);
});

it('rejects an unsigned bounce and leaves the message as it was', function (): void {
    $delivery = Delivery::query()->where('source_id', $this->campaign->id)->where('channel', 'mail')->firstOrFail();
    $body = json_encode(['type' => 'email.bounced', 'data' => ['tags' => ['delivery_id' => $delivery->id]]], JSON_THROW_ON_ERROR);

    $this->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertForbidden();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Created);
});
