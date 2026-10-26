<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Writes one audit entry. Tenant, actor and request come from the request
 * Context, so callers only say what happened. Metadata must never contain
 * survey answers or anything that links an employee to a Guía II/III response.
 */
final class RecordAudit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(string $event, ?Model $subject = null, array $metadata = []): AuditEntry
    {
        $subjectKey = $subject?->getKey();

        return AuditEntry::query()->create([
            'tenant_id' => Context::get('tenant_id'),
            'actor_id' => Context::get('user_id'),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => is_scalar($subjectKey) ? (string) $subjectKey : null,
            'metadata' => $metadata,
            'request_id' => Context::get('request_id'),
        ]);
    }
}
