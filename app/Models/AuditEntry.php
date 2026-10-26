<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed> $metadata
 * @property string|null $request_id
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'actor_id', 'event', 'subject_type', 'subject_id', 'metadata', 'request_id'])]
final class AuditEntry extends Model
{
    use HasUuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
