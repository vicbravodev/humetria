<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Notifications\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The push subscription of one phone of a person: where the push service of the browser (Apple, Google, Mozilla) delivers a
 * notice with no body. `endpoint`, `p256dh` and `auth` identify that phone and never reach a page, a log or the audit trail.
 * `tenant_id` is given when it is made: the trait only filters.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'endpoint', 'p256dh', 'auth', 'last_success_at'])]
#[Hidden(['endpoint', 'p256dh', 'auth'])]
#[UseFactory(PushSubscriptionFactory::class)]
final class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_success_at' => 'immutable_datetime'];
    }
}
