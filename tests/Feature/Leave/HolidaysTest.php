<?php

declare(strict_types=1);

use App\Modules\Leave\Actions\SaveHoliday;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Support\Holidays;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveHoliday::class, Holidays::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
});

/**
 * @return array<string, list<string>>
 */
function holidayErrors(Closure $save): array
{
    try {
        $save();
    } catch (ValidationException $refused) {
        return $refused->errors();
    }

    return [];
}

function holidayOn(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('gives the holidays of the law and of the company in a range, in order', function (): void {
    $holiday = resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), ' Aniversario de la empresa ');

    expect($holiday->refresh()->only(['tenant_id', 'name']))->toBe(['tenant_id' => $this->tenant->id, 'name' => 'Aniversario de la empresa'])
        ->and($holiday->date->toDateString())->toBe('2026-11-12')
        ->and(Holidays::between(holidayOn('2026-11-01'), holidayOn('2026-11-30')))->toBe(['2026-11-12', '2026-11-16']);
});

it('includes both ends of the range and nothing outside it', function (): void {
    resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), 'Aniversario');
    resolve(SaveHoliday::class)->handle(holidayOn('2026-11-17'), 'Puente');

    expect(Holidays::between(holidayOn('2026-11-12'), holidayOn('2026-11-16')))->toBe(['2026-11-12', '2026-11-16'])
        ->and(Holidays::between(holidayOn('2026-11-13'), holidayOn('2026-11-15')))->toBe([])
        ->and(Holidays::between(holidayOn('2026-11-16 15:00'), holidayOn('2026-11-16')))->toBe(['2026-11-16']);
});

it('covers every year of a range that crosses years', function (): void {
    resolve(SaveHoliday::class)->handle(holidayOn('2026-12-31'), 'Fin de año');

    expect(Holidays::between(holidayOn('2026-12-20'), holidayOn('2028-01-01')))->toBe([
        '2026-12-25', '2026-12-31', '2027-01-01', '2027-02-01', '2027-03-15', '2027-05-01', '2027-09-16', '2027-11-15', '2027-12-25', '2028-01-01',
    ]);
});

it('refuses a holiday the law already gives, a repeated date or one without a name', function (): void {
    resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), 'Aniversario');

    expect(holidayErrors(fn () => resolve(SaveHoliday::class)->handle(holidayOn('2026-11-16'), 'Revolución')))->toBe(['date' => ['Ese día ya es descanso obligatorio por ley.']])
        ->and(holidayErrors(fn () => resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), 'Otro')))->toBe(['date' => ['Ya hay un festivo en esa fecha.']])
        ->and(holidayErrors(fn () => resolve(SaveHoliday::class)->handle(holidayOn('2026-11-13'), ' ')))->toBe(['name' => ['Escribe el nombre del festivo.']])
        ->and(holidayErrors(fn () => resolve(SaveHoliday::class)->handle(holidayOn('2026-11-13'), str_repeat('a', 81))))->toBe(['name' => ['El nombre no puede pasar de 80 caracteres.']])
        ->and(LeaveHoliday::query()->count())->toBe(1);
});

it('removes a holiday of the company', function (): void {
    $holiday = resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), 'Aniversario');

    resolve(SaveHoliday::class)->remove($holiday->id);

    expect(LeaveHoliday::query()->count())->toBe(0)
        ->and(Holidays::between(holidayOn('2026-11-01'), holidayOn('2026-11-30')))->toBe(['2026-11-16']);
});

it('rejects in the database two holidays on the same date of a company', function (): void {
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-12']);

    // Another company may have the same date; a savepoint keeps the refused insert from ending the test's transaction.
    expect(LeaveHoliday::factory()->create(['date' => '2026-11-12'])->exists)->toBeTrue()
        ->and(fn () => DB::transaction(fn () => LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-12'])))->toThrow(QueryException::class, 'leave_holidays_tenant_id_date_unique');
});

it('logs what it saved and removed, never the name', function (): void {
    $lines = [];
    Log::listen(function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    });

    $holiday = resolve(SaveHoliday::class)->handle(holidayOn('2026-11-12'), 'Aniversario');
    resolve(SaveHoliday::class)->remove($holiday->id);

    expect($lines)->toBe([
        'leave.holiday_saved {"holiday_id":"'.$holiday->id.'","date":"2026-11-12"}',
        'leave.holiday_removed {"holiday_id":"'.$holiday->id.'","date":"2026-11-12"}',
    ]);
});

it('does not read, count or remove the holidays of another company', function (): void {
    $ofOther = LeaveHoliday::factory()->create(['date' => '2026-11-12']);

    expect(LeaveHoliday::query()->count())->toBe(0)
        ->and(LeaveHoliday::query()->find($ofOther->id))->toBeNull()
        ->and(Holidays::between(holidayOn('2026-11-01'), holidayOn('2026-11-30')))->toBe(['2026-11-16'])
        ->and(fn () => resolve(SaveHoliday::class)->remove($ofOther->id))->toThrow(ModelNotFoundException::class)
        ->and(LeaveHoliday::query()->withoutGlobalScopes()->whereKey($ofOther->id)->exists())->toBeTrue();
});
