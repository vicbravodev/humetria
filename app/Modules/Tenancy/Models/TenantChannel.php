<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One channel (mail, whatsapp) of a company: on or off, its place in the order, the owner's monthly cap and, for WhatsApp, who
 * accepted the words, when, and which version.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $channel
 * @property bool $enabled
 * @property int $preference
 * @property int|null $monthly_cap
 * @property string|null $consent_wording_version
 * @property int|null $enabled_by
 * @property CarbonImmutable|null $enabled_at
 */
#[Fillable(['tenant_id', 'channel', 'enabled', 'preference', 'monthly_cap', 'consent_wording_version', 'enabled_by', 'enabled_at'])]
#[UseFactory(TenantChannelFactory::class)]
final class TenantChannel extends Model
{
    /** @use HasFactory<TenantChannelFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'preference' => 'integer', 'monthly_cap' => 'integer', 'enabled_at' => 'immutable_datetime'];
    }
}
