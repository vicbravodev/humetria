<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Operations\Support\MessagingOverview;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(MessagingOverview::class);

// 12:00 in Mexico City: the month there began on 1 November at 06:00 UTC, and the day at 06:00 UTC.
beforeEach(fn () => $this->travelTo('2026-11-25 18:00 UTC'));

function messagingConsole(): Illuminate\Testing\TestResponse
{
    return test()->actingAs(superadmin())->get(route('operations.messaging'));
}

it('lives at /admin/operations/messaging', function (): void {
    expect(route('operations.messaging', absolute: false))->toBe('/admin/operations/messaging');
});

it('sends guests to the login', function (): void {
    $this->get(route('operations.messaging'))->assertRedirect(route('login'));
});

it('answers 403 to support and to anyone without a platform role', function (User $user): void {
    $this->actingAs($user)->get(route('operations.messaging'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('counts the messages of the month and of the day in Mexico City, by channel', function (): void {
    Delivery::factory()->create(['status' => DeliveryStatus::Read, 'created_at' => '2026-11-25 17:00:00']);
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-02 12:00:00']);
    // Still October in Mexico City.
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-01 05:00:00']);

    messagingConsole()->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('operations/messaging')
        ->where('month', [['channel' => 'whatsapp', 'total' => 2, 'delivered' => 1, 'read' => 1, 'rejected' => 1]])
        ->where('today', [['channel' => 'whatsapp', 'total' => 1, 'delivered' => 1, 'read' => 1, 'rejected' => 0]]));
});

it('lists every template in code with what it sent in 30 days', function (): void {
    Delivery::factory()->create(['template' => 'engagement_invite_v1', 'status' => DeliveryStatus::Delivered]);

    messagingConsole()->assertInertia(fn (Assert $page) => $page
        ->where('templates.0', ['key' => 'survey_invite_v1', 'category' => 'UTILITY', 'pendingInCode' => false, 'total' => 0, 'delivered' => 0, 'rejected' => 0])
        ->where('templates.2', ['key' => 'engagement_invite_v1', 'category' => 'UTILITY', 'pendingInCode' => false, 'total' => 1, 'delivered' => 1, 'rejected' => 0]));
});

it('names the companies with five or more rejections in 7 days, with the way into each', function (): void {
    $textiles = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);
    $quiet = Tenant::factory()->create(['trade_name' => 'Pocas fallas']);
    Delivery::factory()->count(5)->create(['tenant_id' => $textiles->id, 'status' => DeliveryStatus::Failed]);
    Delivery::factory()->count(4)->create(['tenant_id' => $quiet->id, 'status' => DeliveryStatus::Failed]);

    messagingConsole()->assertInertia(fn (Assert $page) => $page
        ->where('companies', [['id' => $textiles->id, 'name' => 'Textiles del Norte', 'rejected' => 5]]));
});

it('takes whole days of Mexico City: the rejections from 6 days ago and the templates from 29, today included', function (): void {
    $in = Tenant::factory()->create(['trade_name' => 'Dentro']);
    $out = Tenant::factory()->create(['trade_name' => 'Fuera']);
    Delivery::factory()->count(5)->create(['tenant_id' => $in->id, 'status' => DeliveryStatus::Failed, 'created_at' => '2026-11-19 06:00:00']);
    Delivery::factory()->count(5)->create(['tenant_id' => $out->id, 'status' => DeliveryStatus::Failed, 'created_at' => '2026-11-19 05:59:59']);
    Delivery::factory()->create(['template' => 'seating_invite_v1', 'created_at' => '2026-10-27 06:00:00']);
    Delivery::factory()->create(['template' => 'seating_invite_v1', 'created_at' => '2026-10-27 05:59:59']);
    Delivery::factory()->create(['template' => 'seating_invite_v1', 'created_at' => '2026-11-25 23:00:00']);

    messagingConsole()->assertInertia(fn (Assert $page) => $page
        ->where('companies', [['id' => $in->id, 'name' => 'Dentro', 'rejected' => 5]])
        ->where('templates.1.total', 2));
});

it('tells the state of the connection with Meta and the opt-outs, in counts', function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false]);
    Pause::query()->create(['reason' => 'platform_cap', 'started_at' => now()->subHour()]);
    WebhookEvent::factory()->create(['provider' => 'meta', 'received_at' => now()->subMinutes(2)]);
    OptOut::factory()->global()->create();
    OptOut::factory()->count(2)->create();

    messagingConsole()->assertInertia(fn (Assert $page) => $page
        ->where('connection', ['enabled' => true, 'paused' => true, 'pauseReason' => 'platform_cap', 'pausedSince' => '2026-11-25T17:00:00+00:00', 'lastEventAt' => '2026-11-25T17:58:00+00:00'])
        ->where('optOuts', ['everyCompany' => 1, 'oneCompany' => 2, 'lastThirtyDays' => 3]));
});

it('opens on the summary, or on the view its tab asks for', function (?string $asked, string $view): void {
    test()->actingAs(superadmin())->get(route('operations.messaging', $asked === null ? [] : ['view' => $asked]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('view', $view)->has('templates')->has('companies'));
})->with([
    'no view' => [null, 'summary'],
    'templates' => ['templates', 'templates'],
    'mail' => ['mail', 'mail'],
    'companies' => ['companies', 'companies'],
    'an unknown one' => ['messages', 'summary'],
]);

it('never gives the page a message, a phone or its hash', function (): void {
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'provider_message_id' => 'wamid.secret']);
    Delivery::factory()->count(5)->create(['tenant_id' => Delivery::query()->sole()->tenant_id, 'status' => DeliveryStatus::Failed]);

    expect(messagingConsole()->getContent())->not->toContain('wamid.secret')->not->toContain('recipient_hash')->not->toContain('invitation_id');
});
