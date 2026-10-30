<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\SendInvitationEmail;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

mutates(SendInvitationEmail::class);

beforeEach(function (): void {
    Queue::fake();
});

it('queues the email once the transaction that wrote the invitation commits', function (): void {
    $invitation = TenantInvitation::factory()->create(['email' => 'ana@acme.mx']);

    DB::transaction(function () use ($invitation): void {
        app(SendInvitationEmail::class)->handle($invitation, 'Acme', 'tok');

        Queue::assertNothingPushed();
    });

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof InvitationEmail
        && $job->notifiables->sole()->routes === ['mail' => 'ana@acme.mx']);
});

it('never queues the email for an invitation that was rolled back', function (): void {
    $invitation = TenantInvitation::factory()->create();

    expect(fn () => DB::transaction(function () use ($invitation): void {
        app(SendInvitationEmail::class)->handle($invitation, 'Acme', 'tok');

        throw new RuntimeException('rolled back');
    }))->toThrow(RuntimeException::class, 'rolled back');

    Queue::assertNothingPushed();
});
