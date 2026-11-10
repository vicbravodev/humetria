<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Support\StatutoryHolidays;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A holiday of the company besides those of the law (criterio C-04), such as the election day. Adding or removing one
 * never changes the working days of a request already made.
 */
final readonly class SaveHoliday
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @throws ValidationException
     */
    public function handle(CarbonImmutable $date, string $name): LeaveHoliday
    {
        $name = trim($name);
        $day = $date->toDateString();

        if ($name === '' || mb_strlen($name) > 80) {
            throw ValidationException::withMessages(['name' => $name === '' ? 'Escribe el nombre del festivo.' : 'El nombre no puede pasar de 80 caracteres.']);
        }

        if (in_array($day, StatutoryHolidays::for($date->year), true)) {
            throw ValidationException::withMessages(['date' => 'Ese día ya es descanso obligatorio por ley.']);
        }

        if (LeaveHoliday::query()->where('date', $day)->exists()) {
            throw ValidationException::withMessages(['date' => 'Ya hay un festivo en esa fecha.']);
        }

        $holiday = LeaveHoliday::query()->create(['tenant_id' => $this->tenant->id(), 'date' => $day, 'name' => $name]);
        Log::info('leave.holiday_saved', ['holiday_id' => $holiday->id, 'date' => $day]);

        return $holiday;
    }

    public function remove(string $holidayId): void
    {
        $holiday = LeaveHoliday::query()->findOrFail($holidayId);
        $holiday->delete();
        Log::info('leave.holiday_removed', ['holiday_id' => $holiday->id, 'date' => $holiday->date->toDateString()]);
    }
}
