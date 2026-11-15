<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\FirstSteps\FirstStepsStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a person did with the guided tour of a company: saw the welcome, hid the card, finished it. One per company and user.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property CarbonImmutable|null $welcomed_at
 * @property CarbonImmutable|null $hidden_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'user_id', 'welcomed_at', 'hidden_at', 'completed_at'])]
#[UseFactory(FirstStepsStateFactory::class)]
final class FirstStepsState extends Model
{
    /** @use HasFactory<FirstStepsStateFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['welcomed_at' => 'immutable_datetime', 'hidden_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
