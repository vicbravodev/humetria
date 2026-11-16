<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use App\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A while the console opens the door of the demo. Only the hash of its code is kept: the code is shown once, when it opens.
 * Company data of the demo: the door enters the demo company before it reads one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $closed_at
 */
#[Fillable(['tenant_id', 'code_hash', 'expires_at'])]
final class DoorSession extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    protected $table = 'demo_door_sessions';

    public function isOpen(): bool
    {
        return $this->closed_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
