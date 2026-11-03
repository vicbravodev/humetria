<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use Carbon\CarbonImmutable;
use Database\Factories\Complaints\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A complaint or report. It has no created_at and no updated_at: the hour it came would tell who sent it, so it keeps only
 * the day (received_on), and its id is a random UUID. The texts are encrypted. The person follows it up with the reference
 * (visible) and a key of which only the hash is kept. It points to no employee, invitation, portal link, campaign or answer.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $channel_id
 * @property string $reference
 * @property string $access_key_hash
 * @property ComplaintType $type
 * @property ComplaintCategory|null $category
 * @property RespondentKind $respondent_kind
 * @property string|null $work_center_id
 * @property IdentityMode $identity_mode
 * @property ComplaintStatus $status
 * @property ComplaintResolution|null $resolution
 * @property bool $on_hold
 * @property string|null $description
 * @property string|null $respondent_text
 * @property string|null $reporter_name
 * @property string|null $reporter_contact
 * @property string|null $involved_text
 * @property CarbonImmutable|null $occurred_on
 * @property CarbonImmutable $received_on
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $closed_on
 * @property CarbonImmutable|null $purged_on
 */
#[Fillable(['tenant_id', 'channel_id', 'reference', 'access_key_hash', 'type', 'category', 'respondent_kind', 'work_center_id', 'identity_mode', 'status', 'resolution', 'on_hold', 'description', 'respondent_text', 'reporter_name', 'reporter_contact', 'involved_text', 'occurred_on', 'received_on', 'acknowledged_at', 'closed_at', 'closed_on', 'purged_on'])]
#[WithoutTimestamps]
#[UseFactory(ComplaintFactory::class)]
final class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => ComplaintType::class,
            'category' => ComplaintCategory::class,
            'respondent_kind' => RespondentKind::class,
            'identity_mode' => IdentityMode::class,
            'status' => ComplaintStatus::class,
            'resolution' => ComplaintResolution::class,
            'on_hold' => 'boolean',
            'description' => 'encrypted',
            'respondent_text' => 'encrypted',
            'reporter_name' => 'encrypted',
            'reporter_contact' => 'encrypted',
            'involved_text' => 'encrypted',
            'occurred_on' => 'immutable_date',
            'received_on' => 'immutable_date',
            'acknowledged_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'closed_on' => 'immutable_date',
            'purged_on' => 'immutable_date',
        ];
    }
}
