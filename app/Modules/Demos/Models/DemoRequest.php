<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Workforce;
use Database\Factories\Demos\DemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A request for a demo from the landing. Platform data: it belongs to no tenant.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $whatsapp
 * @property string $company
 * @property DemoProfile $profile
 * @property Workforce $workforce
 * @property string|null $message
 * @property Carbon $created_at
 */
#[Fillable(['name', 'email', 'whatsapp', 'company', 'profile', 'workforce', 'message'])]
#[UseFactory(DemoRequestFactory::class)]
final class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile' => DemoProfile::class,
            'workforce' => Workforce::class,
            'created_at' => 'datetime',
        ];
    }
}
