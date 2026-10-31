<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The person's written authorization for an in-person visit to their workplace: they confirm it in their portal
 * (`confirmed_at` and the address they confirmed from) or RH attaches the signed PDF (`document_path`).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $workplace_id
 * @property int $requested_by
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $confirmed_at
 * @property string|null $confirmed_ip
 * @property string|null $document_path
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'workplace_id', 'requested_by', 'requested_at', 'confirmed_at', 'confirmed_ip', 'document_path', 'cancelled_at', 'cancelled_by'])]
#[UseFactory(VisitAuthorizationFactory::class)]
final class VisitAuthorization extends Model
{
    /** @use HasFactory<VisitAuthorizationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** Whether the person has authorized the visit, in the portal or on paper. A cancelled request authorizes nothing. */
    public function authorized(): bool
    {
        return $this->cancelled_at === null && ($this->confirmed_at !== null || $this->document_path !== null);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requested_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
