<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\RequestAccessLink;
use App\Modules\WorkerAccess\Jobs\SendRequestedLink;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Notifications\AccessLinkRequested;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(RequestAccessLink::class, SendRequestedLink::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    Notification::fake();
});

it('answers the same, and sends a link only when one active person of the company has the email', function (string $email, bool $sent): void {
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'email' => 'gone@acme.mx']);
    EmployeeFactory::new()->create(['tenant_id' => Tenant::factory()->create()->id, 'email' => 'beto@other.mx']);
    workIn($this->tenant);

    $this->postJson(route('worker.link.request', 'acme'), ['email' => $email])
        ->assertOk()->assertExactJson(['requested' => true])->assertHeader('Cache-Control', 'no-store, private');

    Notification::assertCount($sent ? 1 : 0);
    expect(WorkerAccessLink::query()->count())->toBe($sent ? 1 : 0);
})->with([
    'the person' => ['ana@acme.mx', true],
    'whatever its case' => [' ANA@acme.MX ', true],
    'nobody' => ['nadie@acme.mx', false],
    'a person who left' => ['gone@acme.mx', false],
    'a person of another company' => ['beto@other.mx', false],
]);

it('emails a requested link of 30 minutes, in «usted», that opens the person\'s portal', function (): void {
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'ana@acme.mx'])->assertOk();

    $link = WorkerAccessLink::query()->sole();
    expect($link)->kind->value->toBe('requested')->employee_id->toBe($this->ana->id)->issued_by->toBeNull()
        ->and($link->expires_at->toIso8601String())->toBe('2026-11-16T15:30:00+00:00');
    Notification::assertSentOnDemand(AccessLinkRequested::class, function (AccessLinkRequested $notice, array $channels, object $notifiable): bool {
        $mail = $notice->toMail($notifiable);
        $url = (string) $mail->actionUrl;
        $token = basename((string) parse_url($url, PHP_URL_PATH));

        expect($notifiable->routes['mail'])->toBe('ana@acme.mx')
            ->and($mail->subject)->toBe('Su enlace para entrar a su portal de Acme')
            ->and($mail->greeting)->toBe('Hola, Ana López')
            ->and($mail->actionText)->toBe('Entrar a mi portal')
            ->and($url)->toStartWith(rtrim(config()->string('app.url'), '/').'/me/acme/link/')
            ->and(WorkerAccessLink::findPending($token)?->id)->toBe(WorkerAccessLink::query()->sole()->id)
            ->and(implode(' ', $mail->outroLines))->toContain('una sola vez')->toContain('30 minutos')->toContain('ignore este correo');

        return true;
    });
});

it('revokes the requested links before, and keeps the one RH issued', function (): void {
    $issued = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'ana@acme.mx'])->assertOk();
    $first = WorkerAccessLink::query()->where('kind', 'requested')->sole();

    $this->travel(1)->minutes();
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'ana@acme.mx'])->assertOk();

    expect($first->refresh()->revoked_at)->not->toBeNull()
        ->and($issued->refresh()->revoked_at)->toBeNull()
        ->and(WorkerAccessLink::query()->where('kind', 'requested')->whereNull('revoked_at')->count())->toBe(1);
});

it('queues the email with ids only and retries it, never the token nor the email', function (): void {
    Queue::fake();

    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'ana@acme.mx'])->assertOk();

    Queue::assertPushed(SendRequestedLink::class, function (SendRequestedLink $job): bool {
        expect($job->tenantId)->toBe($this->tenant->id)->and($job->employeeId)->toBe($this->ana->id)
            ->and($job->tries)->toBe(3)->and($job->backoff)->toBe([60, 300])
            ->and(serialize($job))->not->toContain('ana@acme.mx');

        return true;
    });
    expect(WorkerAccessLink::query()->count())->toBe(0);
});

it('sends nothing when the person left or the company was blocked before the job ran', function (string $case): void {
    Queue::fake();
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'ana@acme.mx'])->assertOk();
    $job = Queue::pushed(SendRequestedLink::class)->sole();

    $case === 'left' ? $this->ana->update(['deactivated_at' => now()]) : $this->tenant->update(suspension());
    app()->call([$job, 'handle']);

    Notification::assertNothingSent();
    expect(WorkerAccessLink::query()->count())->toBe(0);
})->with(['left', 'blocked']);

it('logs whether someone was found, never the email', function (): void {
    Log::spy();

    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'nadie@acme.mx'])->assertOk();

    Log::shouldHaveReceived('info')->with('worker_access.link_requested', ['matched' => false]);
});

it('refuses what is not an email, saying so', function (): void {
    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'not an email'])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('slows down one email asked for too often, whatever the address', function (): void {
    foreach (range(1, 5) as $index) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$index}"])->postJson(route('worker.link.request', 'acme'), ['email' => 'nadie@acme.mx'])->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson(route('worker.link.request', 'acme'), ['email' => 'NADIE@acme.mx'])->assertTooManyRequests();
});

it('slows down one address that asks too often', function (): void {
    foreach (range(1, 10) as $index) {
        $this->postJson(route('worker.link.request', 'acme'), ['email' => "nadie{$index}@acme.mx"])->assertOk();
    }

    $this->postJson(route('worker.link.request', 'acme'), ['email' => 'otra@acme.mx'])->assertTooManyRequests();
});
