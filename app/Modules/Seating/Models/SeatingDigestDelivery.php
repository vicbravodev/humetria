<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatingDigestDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * That a staff recipient («owner» or «user:{id}») got the Ley Silla digest of a day, and when.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $recipient
 * @property CarbonImmutable $day
 * @property CarbonImmutable $delivered_at
 */
#[Fillable(['tenant_id', 'recipient', 'day', 'delivered_at'])]
#[UseFactory(SeatingDigestDeliveryFactory::class)]
final class SeatingDigestDelivery extends Model
{
    /** @use HasFactory<SeatingDigestDeliveryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day' => 'immutable_date', 'delivered_at' => 'immutable_datetime'];
    }
}
