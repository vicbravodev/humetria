<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

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
            'tenant_id' => Context::get('tenant_id') ?? $this->company($subject, $metadata),
            'actor_id' => Context::get('user_id'),
            'impersonator_id' => Context::get('impersonator_id'),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => is_scalar($subjectKey) ? (string) $subjectKey : null,
            'metadata' => $metadata,
            'request_id' => Context::get('request_id'),
        ]);
    }

    /**
     * The company an event is about when no request context says so (the superadmin acts on a company from outside it): the
     * company itself, the tenant its subject belongs to, or the one named in the metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function company(?Model $subject, array $metadata): ?string
    {
        $fromSubject = $subject === null ? null : ($subject->getTable() === 'tenants' ? $subject->getKey() : ($subject->getAttributes()['tenant_id'] ?? null));

        foreach ([$fromSubject, $metadata['tenant_id'] ?? null] as $id) {
            if (is_string($id) && Str::isUuid($id)) {
                return $id;
            }
        }

        return null;
    }
}
