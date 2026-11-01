<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\EquipmentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\EquipmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One item of equipment delivered to a teleworker, and whether the person received it or refused it (with the reason).
 * Never deleted: a registration is evidence (criterio C-22).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $teleworker_id
 * @property EquipmentKind $kind
 * @property string $description
 * @property CarbonImmutable $delivered_on
 * @property EquipmentStatus $status
 * @property string|null $refusal_reason
 * @property CarbonImmutable|null $responded_at
 * @property string|null $responded_ip
 * @property int $registered_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'teleworker_id', 'kind', 'description', 'delivered_on', 'status', 'refusal_reason', 'responded_at', 'responded_ip', 'registered_by'])]
#[UseFactory(EquipmentItemFactory::class)]
final class EquipmentItem extends Model
{
    /** @use HasFactory<EquipmentItemFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => EquipmentKind::class, 'status' => EquipmentStatus::class, 'delivered_on' => 'immutable_date', 'responded_at' => 'immutable_datetime'];
    }
}
