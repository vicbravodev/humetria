<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\CreateDelivery;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

mutates(CreateDelivery::class);

beforeEach(function (): void {
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->reference = new InvitationReference(InvitationReference::CAMPAIGN_PARTICIPANT, (string) Str::uuid(), InvitationReference::CAMPAIGN, (string) Str::uuid());
});

it('creates the WhatsApp delivery in the current company, with the hash and never the phone', function (): void {
    $delivery = app(CreateDelivery::class)->handle('whatsapp', $this->reference, 'survey_invite_v1', '+528186875767');

    expect($delivery->tenant_id)->toBe($this->tenant->id)
        ->and($delivery->status)->toBe(DeliveryStatus::Created)
        ->and($delivery->provider_message_id)->toBeNull()
        ->and($delivery->template)->toBe('survey_invite_v1')
        ->and($delivery->invitation_type)->toBe('campaign_participant')
        ->and($delivery->invitation_id)->toBe($this->reference->id)
        ->and($delivery->source_type)->toBe('campaign')
        ->and($delivery->source_id)->toBe($this->reference->sourceId)
        ->and($delivery->recipient_hash)->toBe((new RecipientHash)->current('+528186875767')['hash'])
        ->and($delivery->key_version)->toBe(1)
        ->and(json_encode($delivery->getAttributes(), JSON_THROW_ON_ERROR))->not->toContain('8186875767');
});

it('writes the created event', function (): void {
    $delivery = app(CreateDelivery::class)->handle('whatsapp', $this->reference, 'survey_invite_v1', '+528186875767');

    expect(DeliveryEvent::query()->where('delivery_id', $delivery->id)->pluck('status')->all())->toBe([DeliveryStatus::Created]);
});

it('creates a mail delivery without template or hash', function (): void {
    $delivery = app(CreateDelivery::class)->handle('mail', $this->reference, null, null);

    expect($delivery->channel)->toBe('mail')
        ->and($delivery->template)->toBeNull()
        ->and($delivery->recipient_hash)->toBeNull()
        ->and($delivery->key_version)->toBeNull();
});

it('gives each delivery its own random UUID', function (): void {
    $one = app(CreateDelivery::class)->handle('mail', $this->reference, null, null);
    $two = app(CreateDelivery::class)->handle('mail', $this->reference, null, null);

    expect($one->id)->not->toBe($two->id)->and($one->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4/');
});
