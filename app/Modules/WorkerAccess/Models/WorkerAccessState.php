<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\WorkerAccess\WorkerAccessStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The access of one person: every session that entered before `valid_after` is over. Made the first time it moves.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property CarbonImmutable $valid_after
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'valid_after'])]
#[UseFactory(WorkerAccessStateFactory::class)]
final class WorkerAccessState extends Model
{
    /** @use HasFactory<WorkerAccessStateFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** Ends every session of the person opened until now. */
    public static function moveValidAfter(string $tenantId, string $employeeId): void
    {
        $now = CarbonImmutable::now();

        self::query()->upsert([['id' => Str::uuid()->toString(), 'tenant_id' => $tenantId, 'employee_id' => $employeeId, 'valid_after' => $now, 'created_at' => $now, 'updated_at' => $now]], ['employee_id'], ['valid_after', 'updated_at']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['valid_after' => 'immutable_datetime:Y-m-d H:i:s.uP'];
    }
}
