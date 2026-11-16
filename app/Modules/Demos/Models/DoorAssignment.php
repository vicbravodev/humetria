<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use App\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The door person a visitor took, and when: nothing of what they answered. Company data of the demo, read inside it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $session_id
 * @property string $employee_id
 * @property Carbon $claimed_at
 */
#[Fillable(['tenant_id', 'session_id', 'employee_id', 'claimed_at'])]
final class DoorAssignment extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    public $timestamps = false;

    protected $table = 'demo_door_assignments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }
}
