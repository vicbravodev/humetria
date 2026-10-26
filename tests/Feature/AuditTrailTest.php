<?php

declare(strict_types=1);

use App\Actions\RecordAudit;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;

mutates(RecordAudit::class);

it('records who did what, on which subject, within which request', function (): void {
    $user = User::factory()->create();
    Context::add(['request_id' => 'req-1', 'user_id' => $user->id, 'tenant_id' => '0199b8f2-5f00-7000-8000-000000000001']);

    $entry = app(RecordAudit::class)->handle('results.viewed', $user, ['area' => 'Ventas']);

    expect($entry->fresh())
        ->event->toBe('results.viewed')
        ->actor_id->toBe($user->id)
        ->tenant_id->toBe('0199b8f2-5f00-7000-8000-000000000001')
        ->request_id->toBe('req-1')
        ->subject_type->toBe($user->getMorphClass())
        ->subject_id->toBe((string) $user->id)
        ->metadata->toBe(['area' => 'Ventas'])
        ->created_at->not->toBeNull();
});

it('returns the entry with the subject key as a string, as it is stored', function (): void {
    $user = User::factory()->create();

    $entry = app(RecordAudit::class)->handle('role.changed', $user);

    expect($entry->subject_id)->toBe((string) $user->id);
});

it('records system events without an actor or subject', function (): void {
    $entry = app(RecordAudit::class)->handle('retention.purged');

    expect($entry->fresh())
        ->actor_id->toBeNull()
        ->subject_type->toBeNull()
        ->metadata->toBe([]);
});

it('rejects updates at the database level', function (): void {
    $entry = app(RecordAudit::class)->handle('campaign.launched');

    AuditEntry::query()->whereKey($entry->id)->update(['event' => 'tampered']);
})->throws(QueryException::class, 'audit_entries is append-only');

it('rejects deletes at the database level', function (): void {
    $entry = app(RecordAudit::class)->handle('campaign.launched');

    AuditEntry::query()->whereKey($entry->id)->delete();
})->throws(QueryException::class, 'audit_entries is append-only');
