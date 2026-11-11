<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of leave that uses no vacation balance (criterio C-19). The ones with a key come with the tool and are never
 * changed nor archived; the rest are the company's. No rules by type: only its name and whether it is paid. Only RH and
 * whoever approves see it (criterio C-22).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $key
 * @property string $name
 * @property bool $paid
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'key', 'name', 'paid', 'archived_at'])]
#[UseFactory(LeaveTypeFactory::class)]
final class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** The types every company starts with: key, name and whether they are paid. */
    public const array SEEDED = [
        ['paternity', 'Paternidad', true],
        ['maternity', 'Maternidad', true],
        ['adoption', 'Adopción', true],
        ['special_paid', 'Permiso especial con goce', true],
        ['special_unpaid', 'Permiso especial sin goce', false],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }
}
