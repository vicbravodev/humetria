<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\EvidenceKind;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\EvidenceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the Comisión asked for about a workplace: photos from the person (they arrive through the portal) or a remote
 * check the Comisión itself does. Open until `fulfilled_at`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $workplace_id
 * @property EvidenceKind $kind
 * @property string $note
 * @property int $requested_by
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'workplace_id', 'kind', 'note', 'requested_by', 'requested_at', 'fulfilled_at'])]
#[UseFactory(EvidenceRequestFactory::class)]
final class EvidenceRequest extends Model
{
    /** @use HasFactory<EvidenceRequestFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<EvidencePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(EvidencePhoto::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'requested_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
        ];
    }
}
