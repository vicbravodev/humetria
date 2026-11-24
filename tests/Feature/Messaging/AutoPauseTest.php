<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\ResumeWhatsApp;
use App\Modules\Messaging\Actions\WatchFailures;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Support\WhatsAppSwitch;
use Database\Factories\Messaging\DeliveryEventFactory;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\Log;

mutates(WatchFailures::class, ResumeWhatsApp::class);

beforeEach(function (): void {
    $this->travelTo('2027-03-15 12:00:00');
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'whatsapp.auto_pause' => ['failure_rate_percent' => 5, 'minimum_messages' => 50, 'window_minutes' => 60]]);
});

/** `$sent` WhatsApp messages sent in the last hour, of which `$blocked` came back as blocked or spam (a failed event with that cause). */
function whatsAppHour(int $sent, int $blocked, FailureCause $cause = FailureCause::SpamLimited, string $at = '2027-03-15 11:30:00'): void
{
    foreach (range(1, $sent) as $index) {
        $delivery = DeliveryFactory::new()->create(['status' => DeliveryStatus::Sent]);
        DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id, 'status' => DeliveryStatus::Sent, 'occurred_at' => $at]);

        if ($index <= $blocked) {
            DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id, 'status' => DeliveryStatus::Failed, 'failure_cause' => $cause, 'occurred_at' => $at]);
        }
    }
}

it('pauses at 5 % of the sent messages and not at less, with at least 50 messages: 49/50 messages and 4.9/5 %', function (int $sent, int $blocked, bool $pauses): void {
    whatsAppHour($sent, $blocked);

    expect(resolve(WatchFailures::class)->handle())->toBe($pauses)
        ->and(Pause::query()->whereNull('ended_at')->count())->toBe($pauses ? 1 : 0);
})->with([
    '49 messages, all blocked' => [49, 49, false],
    '50 messages, 2 blocked (4 %)' => [50, 2, false],
    '50 messages, 3 blocked (6 %)' => [50, 3, true],
    '1000 messages, 49 blocked (4.9 %)' => [1000, 49, false],
    '1000 messages, 50 blocked (5 %)' => [1000, 50, true],
    '100 messages, 5 blocked (5 %)' => [100, 5, true],
    '50 messages, none blocked' => [50, 0, false],
]);

it('counts the two causes of a block, and no other failure', function (FailureCause $cause, bool $pauses): void {
    whatsAppHour(100, 10, $cause);

    expect(resolve(WatchFailures::class)->handle())->toBe($pauses);
})->with([
    [FailureCause::SpamLimited, true],
    [FailureCause::EcosystemBlocked, true],
    [FailureCause::NoWhatsappAccount, false],
    [FailureCause::TemplateNotApproved, false],
    [FailureCause::RateLimited, false],
]);

it('looks only at the window: what is older than it does not count', function (): void {
    whatsAppHour(100, 50, at: '2027-03-15 10:59:59');

    expect(resolve(WatchFailures::class)->handle())->toBeFalse();
});

it('does not count the mail', function (): void {
    foreach (range(1, 60) as $ignored) {
        $delivery = DeliveryFactory::new()->mail()->create(['status' => DeliveryStatus::Sent]);
        DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id, 'status' => DeliveryStatus::Sent, 'occurred_at' => '2027-03-15 11:30:00']);
        DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id, 'status' => DeliveryStatus::Failed, 'failure_cause' => FailureCause::SpamLimited, 'occurred_at' => '2027-03-15 11:30:00']);
    }

    expect(resolve(WatchFailures::class)->handle())->toBeFalse();
});

it('opens one pause only, and says so as an error once', function (): void {
    Log::spy();
    whatsAppHour(100, 20);

    expect(resolve(WatchFailures::class)->handle())->toBeTrue()
        ->and(resolve(WatchFailures::class)->handle())->toBeFalse()
        ->and(Pause::query()->count())->toBe(1)
        ->and(Pause::query()->sole()->reason)->toBe('failure_rate')
        ->and(resolve(WhatsAppSwitch::class)->available())->toBeFalse();
    Log::shouldHaveReceived('error')->withArgs(fn (string $event): bool => $event === 'whatsapp.paused_automatically')->once();
});

it('uses the thresholds it is configured with', function (): void {
    config(['whatsapp.auto_pause' => ['failure_rate_percent' => 50, 'minimum_messages' => 10, 'window_minutes' => 60]]);
    whatsAppHour(10, 4);

    expect(resolve(WatchFailures::class)->handle())->toBeFalse();

    whatsAppHour(10, 10);

    expect(resolve(WatchFailures::class)->handle())->toBeTrue();
});

it('is resumed by an operator, who closes the pause, and nothing is sent again by itself', function (): void {
    Log::spy();
    whatsAppHour(100, 20);
    resolve(WatchFailures::class)->handle();

    $this->artisan('messaging:resume')->expectsOutputToContain('Pausa cerrada')->assertSuccessful();

    expect(Pause::query()->whereNull('ended_at')->count())->toBe(0)
        ->and(Pause::query()->sole()->ended_at)->not->toBeNull()
        ->and(resolve(WhatsAppSwitch::class)->available())->toBeTrue();
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'whatsapp.resumed')->once();
});

it('says there was nothing to resume', function (): void {
    $this->artisan('messaging:resume')->expectsOutputToContain('No había ninguna pausa abierta')->assertSuccessful();
});

it('can be paused again after a resume', function (): void {
    whatsAppHour(100, 20);
    resolve(WatchFailures::class)->handle();
    resolve(ResumeWhatsApp::class)->handle();

    expect(resolve(WatchFailures::class)->handle())->toBeTrue()->and(Pause::query()->count())->toBe(2);
});
