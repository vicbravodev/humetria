<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\TracksDelivery;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Jobs\SendWhatsAppMessage;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Messaging\Support\TemplateRegistry;
use App\Modules\Messaging\Support\WhatsAppChannel;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

mutates(WhatsAppChannel::class);

final class ChannelStubNotification extends Notification implements TracksDelivery
{
    public function __construct(private readonly ?InvitationReference $reference) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function deliveryReference(): ?InvitationReference
    {
        return $this->reference;
    }
}

final class ChannelUntrackedNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }
}

beforeEach(function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    $this->tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();
    workIn($this->tenant);
    $this->reference = new InvitationReference(InvitationReference::CAMPAIGN_PARTICIPANT, (string) Str::uuid(), InvitationReference::CAMPAIGN, (string) Str::uuid());
    Queue::fake();
});

it('creates the delivery and queues the job with only the delivery id', function (): void {
    NotificationFacade::routes(['whatsapp' => '+528186875767'])->notify(new ChannelStubNotification($this->reference));

    $delivery = Delivery::query()->sole();
    expect($delivery->channel)->toBe('whatsapp')
        ->and($delivery->template)->toBe('survey_invite_v1')
        ->and($delivery->status)->toBe(DeliveryStatus::Created)
        ->and($delivery->tenant_id)->toBe($this->tenant->id)
        ->and($delivery->invitation_id)->toBe($this->reference->id)
        ->and($delivery->recipient_hash)->toBe((new RecipientHash)->current('+528186875767')['hash']);
    Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job): bool => $job->deliveryId === $delivery->id);
});

it('picks the template of the engagement invitation for its type', function (): void {
    $reference = new InvitationReference(InvitationReference::ENGAGEMENT_INVITATION, (string) Str::uuid(), InvitationReference::ENGAGEMENT_SURVEY, (string) Str::uuid());

    NotificationFacade::routes(['whatsapp' => '+528186875767'])->notify(new ChannelStubNotification($reference));

    expect(Delivery::query()->sole()->template)->toBe('engagement_invite_v1')
        ->and((new TemplateRegistry)->forInvitation('engagement_invitation')->key)->toBe('engagement_invite_v1')
        ->and((new TemplateRegistry)->forInvitation('campaign_participant')->key)->toBe('survey_invite_v1');
});

it('creates nothing and logs a refusal without the phone when the company has no consent', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    Log::spy();

    NotificationFacade::routes(['whatsapp' => '+528186875767'])->notify(new ChannelStubNotification($this->reference));

    expect(Delivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.send_refused' && ! str_contains((string) json_encode($context), '8186875767'))->once();
});

it('creates nothing for a phone it cannot read', function (): void {
    NotificationFacade::routes(['whatsapp' => 'nope'])->notify(new ChannelStubNotification($this->reference));

    expect(Delivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('refuses a notification that does not track its delivery, or has no reference', function (Notification $notification): void {
    NotificationFacade::routes(['whatsapp' => '+528186875767'])->notify($notification);
})->with([
    'untracked' => fn () => new ChannelUntrackedNotification,
    'no reference' => fn () => new ChannelStubNotification(null),
])->throws(LogicException::class);

it('refuses an unknown template type', function (): void {
    (new TemplateRegistry)->forInvitation('nope');
})->throws(InvalidArgumentException::class);
