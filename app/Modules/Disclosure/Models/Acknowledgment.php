<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\AcknowledgmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The request to confirm a version of the policy, and once given, the confirmation (criterio C-53): by the person in their
 * link (`portal`) or recorded by RH from paper (`manual`). It keeps no address. It is never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string $employee_id
 * @property string|null $channel
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property string|null $note
 * @property int|null $recorded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('disclosure_acknowledgments')]
#[Fillable(['tenant_id', 'document_id', 'employee_id', 'channel', 'requested_at', 'acknowledged_at', 'note', 'recorded_by'])]
#[UseFactory(AcknowledgmentFactory::class)]
final class Acknowledgment extends Model
{
    /** @use HasFactory<AcknowledgmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requested_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }
}
