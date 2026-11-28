<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\TracksDelivery;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Listeners\MarkMailSent;
use App\Modules\Messaging\Listeners\OpenMailDelivery;
use App\Modules\Messaging\Listeners\StampMailDelivery;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\MailDeliveryStash;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;

mutates(OpenMailDelivery::class, StampMailDelivery::class, MarkMailSent::class, MailDeliveryStash::class);

final class TrackedTestNotification extends Notification implements TracksDelivery
{
    public function __construct(private readonly ?InvitationReference $reference) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Hola')->line('Su enlace');
    }

    public function deliveryReference(): ?InvitationReference
    {
        return $this->reference;
    }
}

final class UntrackedTestNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Hola')->line('Su enlace');
    }
}

/** The messages the array mailer "sent". */
function sentMessages(): array
{
    /** @var list<SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->all();

    return $messages;
}

beforeEach(function (): void {
    config(['mail.default' => 'array']);
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->reference = new InvitationReference(InvitationReference::CAMPAIGN_PARTICIPANT, (string) Str::uuid(), InvitationReference::CAMPAIGN, (string) Str::uuid());
});

it('creates the mail delivery before sending and puts its id on the message', function (): void {
    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));

    $delivery = Delivery::query()->sole();

    expect($delivery->channel)->toBe('mail')
        ->and($delivery->tenant_id)->toBe($this->tenant->id)
        ->and($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->invitation_id)->toBe($this->reference->id)
        ->and($delivery->recipient_hash)->toBeNull()
        ->and(sentMessages())->toHaveCount(1)
        ->and(sentMessages()[0]->getOriginalMessage()->getHeaders()->get(StampMailDelivery::HEADER)?->getBodyAsString())->toBe($delivery->id);
});

it('follows each mail as its own message', function (): void {
    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));
    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));

    $ids = Delivery::query()->pluck('id')->all();
    $stamped = array_map(fn (SentMessage $sent): ?string => $sent->getOriginalMessage()->getHeaders()->get(StampMailDelivery::HEADER)?->getBodyAsString(), sentMessages());

    expect($ids)->toHaveCount(2)->and($stamped)->toEqualCanonicalizing($ids);
});

it('stamps nothing on a mail that is not followed, and does not leak an id to the next one', function (): void {
    NotificationFacade::route('mail', 'ana@example.com')->notify(new UntrackedTestNotification);
    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification(null));

    expect(Delivery::query()->count())->toBe(0)
        ->and(array_map(fn (SentMessage $sent): bool => $sent->getOriginalMessage()->getHeaders()->has(StampMailDelivery::HEADER), sentMessages()))->toBe([false, false]);
});

it('follows nothing for the demo company, whose mail does not leave', function (): void {
    workIn(Tenant::factory()->demo()->create());

    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));

    expect(Delivery::query()->count())->toBe(0);
});

it('follows only the mail channel', function (): void {
    $notification = new TrackedTestNotification($this->reference);
    event(new Illuminate\Notifications\Events\NotificationSending(new stdClass, $notification, 'database'));

    expect(Delivery::query()->count())->toBe(0);
});

it('keeps a single live row when the send fails and the queue retries it', function (): void {
    $attempts = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('smtp down');
        }
    });

    expect(fn () => NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference)))->toThrow(RuntimeException::class);
    expect(Delivery::query()->sole()->status)->toBe(DeliveryStatus::Created);

    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));

    $delivery = Delivery::query()->sole();
    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and(sentMessages())->toHaveCount(1)
        ->and(sentMessages()[0]->getOriginalMessage()->getHeaders()->get(StampMailDelivery::HEADER)?->getBodyAsString())->toBe($delivery->id);
});

it('does not take a mail that already left for the failed attempt of the next one', function (): void {
    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));
    $first = Delivery::query()->sole();

    NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference));

    expect(Delivery::query()->count())->toBe(2)->and($first->refresh()->status)->toBe(DeliveryStatus::Sent);
});

it('reuses the row of a failed attempt only for the same invitation', function (): void {
    $attempts = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('smtp down');
        }
    });
    $other = new InvitationReference(InvitationReference::CAMPAIGN_PARTICIPANT, (string) Str::uuid(), InvitationReference::CAMPAIGN, $this->reference->sourceId);

    expect(fn () => NotificationFacade::route('mail', 'ana@example.com')->notify(new TrackedTestNotification($this->reference)))->toThrow(RuntimeException::class);
    NotificationFacade::route('mail', 'luis@example.com')->notify(new TrackedTestNotification($other));

    expect(Delivery::query()->count())->toBe(2);
});
