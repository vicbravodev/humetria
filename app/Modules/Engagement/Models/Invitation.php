<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Modules\Engagement\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Engagement\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person invited to a survey: the hash of their one-time link and the coarse group they were invited in. Only the hash
 * is stored; the link itself exists only in the email, and is the same in every email (the invitation and each
 * reminder). `answered` is the only trace of having answered: nothing here leads to the answers.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $survey_id
 * @property string $employee_id
 * @property string|null $token_hash
 * @property InvitationStatus|null $invitation_status
 * @property string|null $invitation_batch
 * @property CarbonImmutable|null $invited_at
 * @property bool $answered
 * @property bool $cancelled
 * @property string|null $work_center_id
 * @property string|null $area_id
 * @property string|null $tenure_band
 * @property string|null $manager_group_id
 * @property string|null $comment_group_id
 * @property string|null $about_manager_id
 * @property CarbonImmutable|null $created_at
 */
#[Table('engagement_invitations')]
#[Fillable(['tenant_id', 'survey_id', 'employee_id', 'token_hash', 'invitation_status', 'invitation_batch', 'invited_at', 'answered', 'cancelled', 'work_center_id', 'area_id', 'tenure_band', 'manager_group_id', 'comment_group_id', 'about_manager_id'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(InvitationFactory::class)]
final class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    /** Marking an invitation answered must leave no hour behind. */
    public const null UPDATED_AT = null;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The person's link token: derived from the invitation's id with the application's secret key, so every email
     * carries the same link and nothing but its hash is ever stored.
     */
    public function surveyToken(): string
    {
        return hash_hmac('sha256', "engagement-link:{$this->id}", config()->string('app.key'));
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered' => 'boolean',
            'cancelled' => 'boolean',
            'invited_at' => 'immutable_datetime',
            'invitation_status' => InvitationStatus::class,
        ];
    }
}
