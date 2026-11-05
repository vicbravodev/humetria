<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Disclosure\Enums\Medium;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\DisseminationRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A poster, bulletin, leaflet or meeting that put information in front of the people of a work center (criterio C-52): the
 * day and the incisos of 5.7 it covered. Evidence: never changed or deleted (a trigger).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property Medium $medium
 * @property list<string> $covered_items
 * @property CarbonImmutable $done_on
 * @property string|null $note
 * @property string|null $evidence_path The photo or PDF on the private disk; the scanned sheet of a list of signatures.
 * @property int|null $signatures How many signed, for a list of signatures.
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('disclosure_dissemination_records')]
#[Fillable(['tenant_id', 'work_center_id', 'medium', 'covered_items', 'done_on', 'note', 'evidence_path', 'signatures', 'recorded_by'])]
#[UseFactory(DisseminationRecordFactory::class)]
final class DisseminationRecord extends Model
{
    /** @use HasFactory<DisseminationRecordFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['medium' => Medium::class, 'covered_items' => 'array', 'done_on' => 'immutable_date', 'signatures' => 'integer'];
    }
}
