<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Assistant\AiFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The rating a person gave to an answer of Humi: up or down, and a reason from a closed list when down.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int|null $participant_id
 * @property string $conversation_id
 * @property string $message_id
 * @property string $rating
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'participant_id', 'conversation_id', 'message_id', 'rating', 'reason', 'created_at'])]
#[UseFactory(AiFeedbackFactory::class)]
final class AiFeedback extends Model
{
    /** @use HasFactory<AiFeedbackFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_feedback';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
