<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Complaints\ComplaintHandlerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person the company designated to handle complaints, for the whole company (no work center) or for one. Being designated
 * opens no case: a case opens only to a handler assigned to it. A designation ends, it is never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property string|null $work_center_id
 * @property int $designated_by
 * @property CarbonImmutable $designated_at
 * @property CarbonImmutable|null $ended_at
 */
#[Fillable(['tenant_id', 'user_id', 'work_center_id', 'designated_by', 'designated_at', 'ended_at'])]
#[WithoutTimestamps]
#[UseFactory(ComplaintHandlerFactory::class)]
final class ComplaintHandler extends Model
{
    /** @use HasFactory<ComplaintHandlerFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['designated_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }
}
