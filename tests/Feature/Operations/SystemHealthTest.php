<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\User;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Operations\Contracts\SchedulerHeartbeat;
use App\Modules\Operations\Support\CachedHeartbeat;
use App\Modules\Operations\Support\FailedJobs;
use App\Modules\Operations\Support\LocalCalendar;
use App\Modules\Operations\Support\QueueWorkload;
use App\Modules\Operations\Support\SystemHealth;
use App\Modules\Operations\Support\TrafficRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

mutates(SystemHealth::class, CachedHeartbeat::class, FailedJobs::class, LocalCalendar::class, QueueWorkload::class, TrafficRows::class);

beforeEach(function (): void {
    // 12:00 in Mexico City: the day there began at 06:00 UTC.
    $this->travelTo('2026-11-25 18:00 UTC');
    horizonAnswers([(object) ['status' => 'running']], [['name' => 'default', 'length' => 3, 'wait' => 4], ['name' => 'whatsapp', 'length' => 0, 'wait' => 0]]);
});

/**
 * @param  list<object>  $masters
 * @param  list<array{name: string, length: int, wait: int}>  $queues
 */
function horizonAnswers(array $masters, array $queues): void
{
    test()->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn($masters);
    test()->mock(WorkloadRepository::class)->shouldReceive('get')->andReturn($queues);
}

function failedJob(string $class, string $queue, string $failedAt): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => $queue,
        'payload' => json_encode(['displayName' => $class, 'data' => ['command' => 'secret: ana@example.com']]),
        'exception' => 'RuntimeException: could not write to ana@example.com',
        'failed_at' => $failedAt,
    ]);
}

function health(): Illuminate\Testing\TestResponse
{
    return test()->actingAs(superadmin())->get(route('operations.health'));
}

it('lives at /admin/operations/health', function (): void {
    expect(route('operations.health', absolute: false))->toBe('/admin/operations/health');
});

it('sends guests to the login', function (): void {
    $this->get(route('operations.health'))->assertRedirect(route('login'));
});

it('answers 403 to support and to anyone without a platform role', function (User $user): void {
    $this->actingAs($user)->get(route('operations.health'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('reads the queues from Horizon: its status and each queue with its length and wait', function (): void {
    health()->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('operations/health')
        ->where('queue.status', 'running')
        ->where('queue.waiting', 3)
        ->where('queue.queues', [['name' => 'default', 'length' => 3, 'wait' => 4], ['name' => 'whatsapp', 'length' => 0, 'wait' => 0]]));
});

it('says Horizon is paused or not running', function (array $masters, string $status): void {
    horizonAnswers($masters, []);

    health()->assertInertia(fn (Assert $page) => $page->where('queue.status', $status));
})->with([
    'paused' => [[(object) ['status' => 'paused']], 'paused'],
    'no master' => [[], 'inactive'],
]);

it('says Horizon does not answer when Redis is down, instead of failing the page', function (): void {
    test()->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andThrow(new RuntimeException('Connection refused'));
    test()->mock(WorkloadRepository::class)->shouldReceive('get')->andThrow(new RuntimeException('Connection refused'));

    health()->assertOk()->assertInertia(fn (Assert $page) => $page->where('queue.status', 'unreachable')->where('queue.queues', []));
});

it('sums the failed jobs of the day and groups those of the week by job, never with their payload or exception', function (): void {
    failedJob('App\Modules\Campaigns\Jobs\SendInvitation', 'default', '2026-11-25 17:00:00');
    failedJob('App\Modules\Campaigns\Jobs\SendInvitation', 'default', '2026-11-25 07:00:00');
    failedJob('App\Modules\Messaging\Jobs\SendWhatsAppMessage', 'whatsapp', '2026-11-25 05:00:00');
    failedJob('App\Modules\Messaging\Jobs\SendWhatsAppMessage', 'whatsapp', '2026-11-10 05:00:00');

    $response = health();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('failedJobs.today', 2)
        ->where('failedJobs.week', [
            ['job' => 'SendInvitation', 'module' => 'Campaigns', 'queue' => 'default', 'count' => 2, 'lastFailedAt' => '2026-11-25T17:00:00+00:00'],
            ['job' => 'SendWhatsAppMessage', 'module' => 'Messaging', 'queue' => 'whatsapp', 'count' => 1, 'lastFailedAt' => '2026-11-25T05:00:00+00:00'],
        ]));
    expect($response->getContent())->not->toContain('ana@example.com');
});

it('names at most ten kinds of failed job, the ones that failed most', function (): void {
    foreach (range(1, 11) as $kind) {
        foreach (range(1, $kind) as $failure) {
            failedJob("App\\Modules\\Campaigns\\Jobs\\Job{$kind}", 'default', '2026-11-25 17:00:00');
        }
    }

    health()->assertInertia(fn (Assert $page) => $page
        ->count('failedJobs.week', 10)
        ->where('failedJobs.week.0.job', 'Job11')
        ->where('failedJobs.week.9.job', 'Job2'));
});

it('reads the last beat of the scheduler and says it is late after 15 minutes', function (): void {
    health()->assertInertia(fn (Assert $page) => $page->where('heartbeat', ['lastAt' => null, 'late' => true]));

    app(SchedulerHeartbeat::class)->beat();
    health()->assertInertia(fn (Assert $page) => $page->where('heartbeat', ['lastAt' => '2026-11-25T18:00:00+00:00', 'late' => false]));

    $this->travel(15)->minutes();
    health()->assertInertia(fn (Assert $page) => $page->where('heartbeat.late', false));

    $this->travel(1)->minutes();
    health()->assertInertia(fn (Assert $page) => $page->where('heartbeat.late', true));
});

it('keeps the beat of the scheduler when its command runs', function (): void {
    $this->artisan('schedule:heartbeat')->assertSuccessful();

    expect(app(SchedulerHeartbeat::class)->lastBeatAt()?->toIso8601String())->toBe('2026-11-25T18:00:00+00:00');
});

it('says when Meta and Resend last reached their webhooks', function (): void {
    WebhookEvent::factory()->create(['provider' => 'resend', 'received_at' => now()->subMinutes(3)]);

    health()->assertInertia(fn (Assert $page) => $page->where('webhooks', ['meta' => null, 'resend' => '2026-11-25T17:57:00+00:00']));
});

it('says whether WhatsApp is on, paused by the operator or by itself', function (): void {
    config(['whatsapp.enabled' => false]);
    health()->assertInertia(fn (Assert $page) => $page->where('whatsApp', ['enabled' => false, 'paused' => false, 'pauseReason' => null]));

    config(['whatsapp.enabled' => true]);
    Pause::query()->create(['reason' => 'failure_rate', 'started_at' => now()]);
    health()->assertInertia(fn (Assert $page) => $page->where('whatsApp', ['enabled' => true, 'paused' => true, 'pauseReason' => 'failure_rate']));
});

it('counts the mail and WhatsApp of the day in Mexico City by status, never a message', function (): void {
    Delivery::factory()->mail()->create(['status' => DeliveryStatus::Delivered, 'created_at' => '2026-11-25 07:00:00']);
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-25 17:00:00']);
    // Before 06:00 UTC it was still yesterday in Mexico City.
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-25 05:00:00']);

    $response = health();

    $response->assertInertia(fn (Assert $page) => $page->where('today', [
        ['channel' => 'mail', 'total' => 1, 'delivered' => 1, 'read' => 0, 'rejected' => 0],
        ['channel' => 'whatsapp', 'total' => 1, 'delivered' => 0, 'read' => 0, 'rejected' => 1],
    ]));
    expect($response->getContent())->not->toContain('recipient_hash')->not->toContain('wamid.');
});

it('is forbidden while impersonating', function (): void {
    expect(ForbidWhileImpersonating::guards('operations.health'))->toBeTrue()
        ->and(ForbidWhileImpersonating::guards('operations.messaging'))->toBeTrue();
});

it('counts the mail and WhatsApp of each of the last 24 hours, this one included, with the share that pauses WhatsApp', function (): void {
    Delivery::factory()->mail()->create(['status' => DeliveryStatus::Bounced, 'created_at' => '2026-11-25 18:00:00']);
    Delivery::factory()->create(['status' => DeliveryStatus::Delivered, 'created_at' => '2026-11-25 17:59:59']);
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-24 19:00:00']);
    // 24 hours ago, out of the window.
    Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'created_at' => '2026-11-24 18:59:59']);

    health()->assertInertia(fn (Assert $page) => $page
        ->where('rejectionLimit', 5)
        ->has('hours', 24)
        ->where('hours.0', ['startsAt' => '2026-11-24T19:00:00+00:00', 'mail' => ['total' => 0, 'rejected' => 0], 'whatsapp' => ['total' => 1, 'rejected' => 1]])
        ->where('hours.22', ['startsAt' => '2026-11-25T17:00:00+00:00', 'mail' => ['total' => 0, 'rejected' => 0], 'whatsapp' => ['total' => 1, 'rejected' => 0]])
        ->where('hours.23', ['startsAt' => '2026-11-25T18:00:00+00:00', 'mail' => ['total' => 1, 'rejected' => 1], 'whatsapp' => ['total' => 0, 'rejected' => 0]]));
});

it('says the scheduler is late only after 15 whole minutes', function (): void {
    app(SchedulerHeartbeat::class)->beat();
    $this->travel(15)->minutes();

    health()->assertInertia(fn (Assert $page) => $page->where('heartbeat.late', false));
});
