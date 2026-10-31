<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\AckChannel;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\AcknowledgementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The request that a person confirms they read one version of a document, and their confirmation once given. Exactly one
 * of `employee_id` and `user_id` is set. Never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string|null $employee_id
 * @property int|null $user_id
 * @property AckChannel|null $channel
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property string|null $ip
 * @property string|null $note
 * @property int|null $recorded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'document_id', 'employee_id', 'user_id', 'channel', 'requested_at', 'acknowledged_at', 'ip', 'note', 'recorded_by'])]
#[UseFactory(AcknowledgementFactory::class)]
final class Acknowledgement extends Model
{
    /** @use HasFactory<AcknowledgementFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['channel' => AckChannel::class, 'requested_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }
}
