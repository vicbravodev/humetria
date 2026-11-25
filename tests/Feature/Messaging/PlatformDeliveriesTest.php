<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Messaging\Data\CompanyRejections;
use App\Modules\Messaging\Data\TemplateTraffic;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\PlatformTraffic;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(PlatformTraffic::class);

beforeEach(fn () => $this->travelTo('2026-11-25 18:00 UTC'));

function deliveries(): PlatformDeliveries
{
    return app(PlatformDeliveries::class);
}

/** @param array<string, mixed> $attributes */
function delivered(int $count, DeliveryStatus $status, array $attributes = []): void
{
    Delivery::factory()->count($count)->create(['status' => $status, ...$attributes]);
}

it('counts the messages of a range by channel: total, delivered (read included), read and rejected', function (): void {
    $tenant = Tenant::factory()->create();
    $in = ['tenant_id' => $tenant->id];
    delivered(2, DeliveryStatus::Delivered, $in);
    delivered(1, DeliveryStatus::Read, $in);
    delivered(1, DeliveryStatus::Sent, $in);
    // What did not arrive: failed, bounced, complained and paused (rule 10 of the spec).
    delivered(1, DeliveryStatus::Failed, $in);
    delivered(1, DeliveryStatus::Paused, $in);
    Delivery::factory()->mail()->create([...$in, 'status' => DeliveryStatus::Bounced]);
    Delivery::factory()->mail()->create([...$in, 'status' => DeliveryStatus::Complained]);
    Delivery::factory()->mail()->create([...$in, 'status' => DeliveryStatus::Delivered]);
    Delivery::factory()->create([...$in, 'status' => DeliveryStatus::Failed, 'created_at' => now()->subDays(2)]);

    expect(deliveries()->byChannel(now()->subDay()->toImmutable(), now()->addMinute()->toImmutable()))->toEqual([
        new ChannelTraffic('mail', 3, 1, 0, 2),
        new ChannelTraffic('whatsapp', 6, 3, 1, 2),
    ]);
});

it('leaves a channel with no messages in the range out', function (): void {
    expect(deliveries()->byChannel(now()->subDay()->toImmutable(), now()->addMinute()->toImmutable()))->toBe([]);
});

it('counts every template of the registry over a range, a template that sent nothing at zero', function (): void {
    delivered(2, DeliveryStatus::Delivered, ['template' => 'survey_invite_v1']);
    delivered(1, DeliveryStatus::Failed, ['template' => 'survey_invite_v1']);
    delivered(1, DeliveryStatus::Failed, ['template' => 'survey_invite_v1', 'created_at' => now()->subDays(40)]);

    $templates = collect(deliveries()->byTemplate(now()->subDays(30)->toImmutable(), now()->addMinute()->toImmutable()))->keyBy('key');

    expect($templates->get('survey_invite_v1'))->toEqual(new TemplateTraffic('survey_invite_v1', 'UTILITY', false, 3, 2, 1))
        ->and($templates->get('engagement_invite_v1'))->toEqual(new TemplateTraffic('engagement_invite_v1', 'UTILITY', false, 0, 0, 0))
        ->and($templates->get('worker_invite_v1')?->pendingInCode)->toBeTrue()
        ->and($templates->get('proof_code_v1')?->category)->toBe('AUTHENTICATION');
});

it('names the companies with the most rejections, only from five up, most first', function (): void {
    $many = Tenant::factory()->create();
    $five = Tenant::factory()->create();
    $four = Tenant::factory()->create();
    delivered(7, DeliveryStatus::Failed, ['tenant_id' => $many->id]);
    delivered(5, DeliveryStatus::Paused, ['tenant_id' => $five->id]);
    delivered(4, DeliveryStatus::Failed, ['tenant_id' => $four->id]);
    delivered(9, DeliveryStatus::Delivered, ['tenant_id' => $four->id]);

    expect(deliveries()->mostRejected(now()->subDays(7)->toImmutable(), now()->addMinute()->toImmutable(), 10))->toEqual([
        new CompanyRejections($many->id, 7),
        new CompanyRejections($five->id, 5),
    ])->and(deliveries()->mostRejected(now()->subDays(7)->toImmutable(), now()->addMinute()->toImmutable(), 1))->toHaveCount(1);
});

it('says when each provider last sent an event, null when it never did', function (): void {
    WebhookEvent::factory()->create(['provider' => 'meta', 'received_at' => now()->subMinutes(5)]);
    WebhookEvent::factory()->create(['provider' => 'meta', 'received_at' => now()->subMinutes(30)]);

    expect(deliveries()->lastWebhookAt('meta'))->toEqual(CarbonImmutable::now()->subMinutes(5))
        ->and(deliveries()->lastWebhookAt('resend'))->toBeNull();
});

it('says whether WhatsApp is on and whether a pause is open, with its reason', function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false]);
    expect((array) deliveries()->connection())->toMatchArray(['enabled' => true, 'pausedByOperator' => false, 'pauseReason' => null, 'pausedSince' => null]);

    Pause::query()->create(['reason' => 'failure_rate', 'started_at' => now()->subHour()]);
    config(['whatsapp.paused' => true]);

    expect((array) deliveries()->connection())->toMatchArray(['enabled' => true, 'pausedByOperator' => true, 'pauseReason' => 'failure_rate'])
        ->and(deliveries()->connection()->pausedSince)->toEqual(CarbonImmutable::now()->subHour());
});

it('counts the opt-outs: from every company, from one, and the last 30 days', function (): void {
    OptOut::factory()->global()->create();
    OptOut::factory()->count(2)->create();
    OptOut::factory()->create(['created_at' => now()->subDays(31)]);

    expect((array) deliveries()->optOuts())->toMatchArray(['everyCompany' => 1, 'oneCompany' => 3, 'lastThirtyDays' => 3]);
});
