<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\MaritalStatus;
use App\Modules\Telework\Enums\TeleworkerStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One telework period of an employee. A person who returns to telework opens a new period, so the roster keeps its history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $work_center_id
 * @property TeleworkerStatus $status
 * @property int $telework_percentage
 * @property MaritalStatus|null $marital_status
 * @property string|null $activities
 * @property string|null $job_profile
 * @property string|null $contact_phone
 * @property string|null $street
 * @property string|null $exterior_number
 * @property string|null $interior_number
 * @property string|null $neighborhood
 * @property string|null $postal_code
 * @property string|null $municipality
 * @property string|null $state
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property EndReason|null $end_reason
 * @property string|null $end_note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'work_center_id', 'status', 'telework_percentage', 'marital_status', 'activities', 'job_profile', 'contact_phone', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'municipality', 'state', 'started_at', 'ended_at', 'end_reason', 'end_note'])]
#[UseFactory(TeleworkerFactory::class)]
final class Teleworker extends Model
{
    /** @use HasFactory<TeleworkerFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<Workplace, $this>
     */
    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeleworkerStatus::class,
            'telework_percentage' => 'integer',
            'marital_status' => MaritalStatus::class,
            'end_reason' => EndReason::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
