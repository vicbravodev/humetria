<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A department of the company ("Producción", "Almacén"), shared by its work centers.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'name'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(AreaFactory::class)]
final class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;
}
