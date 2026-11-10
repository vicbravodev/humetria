<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What RH adjusts of Vacaciones: the leave types, the holidays of the company and the working week of the law, with since
 * which day the periods are granted (shown, never edited from here). Names and dates, never an amount. The tabs count what
 * is in use, whichever is open.
 *
 * @phpstan-type TypeRow array{id: string, name: string, paid: bool, statutory: bool, archived: bool}
 * @phpstan-type HolidayRow array{id: string, date: string, name: string}
 */
final readonly class SettingsPages
{
    public const int PER_PAGE = 15;

    /** The tabs, the first is the default. */
    public const array TABS = ['types', 'holidays', 'week'];

    /**
     * @return array{tab: string, counts: array{types: int, holidays: int}, types: LengthAwarePaginator<int, TypeRow>, holidays: LengthAwarePaginator<int, HolidayRow>, setting: array{trackingSince: string, statutoryWorkWeek: string}, workWeeks: list<array{value: string, label: string}>}
     */
    public function show(string $tab, LeaveSetting $setting): array
    {
        return [
            'tab' => in_array($tab, self::TABS, true) ? $tab : self::TABS[0],
            'counts' => ['types' => LeaveType::query()->whereNull('archived_at')->count(), 'holidays' => LeaveHoliday::query()->count()],
            // Those in use first, then the archived ones; the law's own come before the company's.
            'types' => LeaveType::query()->orderByRaw('archived_at is not null')->orderByRaw('"key" is null')->orderBy('name')->paginate(self::PER_PAGE, pageName: 'types')->withQueryString()
                ->through(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->name, 'paid' => $type->paid, 'statutory' => $type->key !== null, 'archived' => $type->archived_at !== null]),
            'holidays' => LeaveHoliday::query()->orderByDesc('date')->paginate(self::PER_PAGE, pageName: 'holidays')->withQueryString()
                ->through(fn (LeaveHoliday $holiday): array => ['id' => $holiday->id, 'date' => $holiday->date->toDateString(), 'name' => $holiday->name]),
            'setting' => ['trackingSince' => $setting->tracking_since->toDateString(), 'statutoryWorkWeek' => $setting->statutory_work_week->value],
            'workWeeks' => [
                ['value' => WorkWeek::MonFri->value, 'label' => 'De lunes a viernes'],
                ['value' => WorkWeek::MonSat->value, 'label' => 'De lunes a sábado'],
            ],
        ];
    }
}
