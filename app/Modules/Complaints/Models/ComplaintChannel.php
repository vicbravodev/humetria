<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Complaints\Enums\ChannelStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Complaints\ComplaintChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The company's channel for complaints and reports (NOM-035 8.1 b). Its access code is what the poster's QR carries: the
 * credential of the company, rotated when the poster leaks.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $access_code
 * @property CarbonImmutable|null $enabled_at
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $published_on
 * @property CarbonImmutable|null $access_code_rotated_on
 * @property string $procedure
 * @property int $acknowledge_within_days
 * @property int $resolve_within_days
 * @property int $retention_months
 */
#[Fillable(['tenant_id', 'access_code', 'enabled_at', 'paused_at', 'published_on', 'access_code_rotated_on', 'procedure', 'acknowledge_within_days', 'resolve_within_days', 'retention_months'])]
#[UseFactory(ComplaintChannelFactory::class)]
final class ComplaintChannel extends Model
{
    /** @use HasFactory<ComplaintChannelFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime', 'published_on' => 'immutable_date', 'access_code_rotated_on' => 'immutable_date'];
    }

    public function status(): ChannelStatus
    {
        return match (true) {
            $this->enabled_at === null => ChannelStatus::Inactive,
            $this->paused_at !== null => ChannelStatus::Paused,
            default => ChannelStatus::Active,
        };
    }

    public function isOpen(): bool
    {
        return $this->status() === ChannelStatus::Active;
    }

    /** What the QR and the link of the poster point at. */
    public function publicUrl(): string
    {
        return url('/c/'.$this->access_code);
    }
}
