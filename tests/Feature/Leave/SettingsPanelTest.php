<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Http\Controllers\SettingsController;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\SettingsPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(SettingsController::class, SettingsPages::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->setting = LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2026-03-01']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function leaveSettingsProps(array $query = []): array
{
    return test()->actingAs(test()->hr)->get(route('tenant.vacations.settings.show', [test()->tenant, ...$query]))->assertOk()->inertiaProps();
}

it('lives under the vacations of the company and opens on the leave types', function (): void {
    expect(route('tenant.vacations.settings.show', $this->tenant, absolute: false))->toBe('/admin/acme/vacations/settings');

    $props = leaveSettingsProps();

    expect($props['tab'])->toBe('types')->and(leaveSettingsProps(['tab' => 'nope'])['tab'])->toBe('types');
});

it('lists the leave types, the ones of the law marked, and counts each tab', function (): void {
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mudanza', 'paid' => false]);
    LeaveType::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Vieja']);
    LeaveType::factory()->create(['name' => 'De otra empresa']);
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id]);

    $props = leaveSettingsProps();
    $types = collect($props['types']['data'])->keyBy('name');

    expect($props['counts'])->toBe(['types' => 6, 'holidays' => 1])
        ->and($types)->toHaveCount(7)
        ->and($types['Mudanza'])->toMatchArray(['paid' => false, 'statutory' => false, 'archived' => false])
        ->and($types['Maternidad'])->toMatchArray(['paid' => true, 'statutory' => true])
        ->and($types['Vieja']['archived'])->toBeTrue()
        ->and($types->has('De otra empresa'))->toBeFalse();
});

it('shows since which day the company tracks vacations and its working week', function (): void {
    $props = leaveSettingsProps(['tab' => 'week']);

    expect($props['setting'])->toBe(['trackingSince' => '2026-03-01', 'statutoryWorkWeek' => 'mon_fri'])
        ->and($props['workWeeks'])->toBe([['value' => 'mon_fri', 'label' => 'De lunes a viernes'], ['value' => 'mon_sat', 'label' => 'De lunes a sábado']]);
});

it('changes the working week of the law and never the start of the tracking', function (): void {
    $this->actingAs($this->hr)->patch(route('tenant.vacations.settings.update', $this->tenant), ['statutory_work_week' => 'mon_sat', 'tracking_since' => '2020-01-01'])->assertRedirect();

    expect($this->setting->refresh()->statutory_work_week)->toBe(WorkWeek::MonSat)
        ->and($this->setting->tracking_since->toDateString())->toBe('2026-03-01');
});

it('refuses a working week that does not exist', function (): void {
    $this->actingAs($this->hr)->patch(route('tenant.vacations.settings.update', $this->tenant), ['statutory_work_week' => 'sun'])->assertSessionHasErrors('statutory_work_week');

    expect($this->setting->refresh()->statutory_work_week)->toBe(WorkWeek::MonFri);
});

it('adds, changes and archives a leave type of its own', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.types.store', $this->tenant), ['name' => 'Mudanza', 'paid' => '1'])->assertRedirect();
    $type = LeaveType::query()->where('name', 'Mudanza')->sole();

    expect($type->paid)->toBeTrue()->and($type->key)->toBeNull();

    $this->actingAs($this->hr)->put(route('tenant.vacations.types.update', [$this->tenant, $type]), ['name' => 'Cambio de casa'])->assertRedirect();
    expect($type->refresh()->only(['name', 'paid']))->toBe(['name' => 'Cambio de casa', 'paid' => false]);

    $this->actingAs($this->hr)->post(route('tenant.vacations.types.archive', [$this->tenant, $type]))->assertRedirect();
    expect($type->refresh()->archived_at)->not->toBeNull();
});

it('sends back the error of a leave type in its field, in Spanish', function (): void {
    // The types of the law exist from the first time the panel is opened.
    resolve(LeaveDefaults::class)->ensure();
    $this->actingAs($this->hr)->post(route('tenant.vacations.types.store', $this->tenant), ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($this->hr)->post(route('tenant.vacations.types.store', $this->tenant), ['name' => 'Maternidad'])->assertSessionHasErrors(['name' => 'Ya hay un permiso con ese nombre.']);

    $law = LeaveType::query()->where('key', 'maternity')->sole();
    $this->actingAs($this->hr)->put(route('tenant.vacations.types.update', [$this->tenant, $law]), ['name' => 'Otro'])->assertSessionHasErrors(['name' => 'Este permiso viene de la ley: no se puede cambiar.']);
    $this->actingAs($this->hr)->post(route('tenant.vacations.types.archive', [$this->tenant, $law]))->assertSessionHasErrors(['type_id' => 'Este permiso viene de la ley: no se puede archivar.']);

    expect($law->refresh()->only(['name', 'archived_at']))->toBe(['name' => 'Maternidad', 'archived_at' => null]);
});

it('adds and removes a holiday of the company', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.holidays.store', $this->tenant), ['date' => '2026-11-12', 'name' => 'Aniversario'])->assertRedirect();
    $holiday = LeaveHoliday::query()->sole();

    expect($holiday->only(['name']))->toBe(['name' => 'Aniversario'])->and($holiday->date->toDateString())->toBe('2026-11-12');

    $this->actingAs($this->hr)->delete(route('tenant.vacations.holidays.destroy', [$this->tenant, $holiday]))->assertRedirect();
    expect(LeaveHoliday::query()->count())->toBe(0);
});

it('sends back the error of a holiday in its field, in Spanish', function (string $date, string $field, string $message): void {
    LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-12']);

    $this->actingAs($this->hr)->post(route('tenant.vacations.holidays.store', $this->tenant), ['date' => $date, 'name' => 'Otro'])->assertSessionHasErrors([$field => $message]);

    expect(LeaveHoliday::query()->count())->toBe(1);
})->with([
    'a day already a holiday' => ['2026-11-12', 'date', 'Ya hay un festivo en esa fecha.'],
    'a day of rest of the law' => ['2026-12-25', 'date', 'Ese día ya es descanso obligatorio por ley.'],
]);

it('validates the shape of a holiday', function (array $data, string $field): void {
    $this->actingAs($this->hr)->post(route('tenant.vacations.holidays.store', $this->tenant), $data)->assertSessionHasErrors($field);
})->with([
    'no date' => [['name' => 'X'], 'date'],
    'a date that is not one' => [['date' => 'mañana', 'name' => 'X'], 'date'],
    'no name' => [['date' => '2026-11-13'], 'name'],
]);

it('lets a viewer read the settings but not change any', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
    $holiday = LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id]);

    $props = $this->actingAs($viewer)->get(route('tenant.vacations.settings.show', $this->tenant))->assertOk()->inertiaProps();
    expect($props['canManage'])->toBeFalse();

    $this->actingAs($viewer)->patch(route('tenant.vacations.settings.update', $this->tenant), ['statutory_work_week' => 'mon_sat'])->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.vacations.types.store', $this->tenant), ['name' => 'X'])->assertForbidden();
    $this->actingAs($viewer)->put(route('tenant.vacations.types.update', [$this->tenant, $type]), ['name' => 'X'])->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.vacations.types.archive', [$this->tenant, $type]))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.vacations.holidays.store', $this->tenant), ['date' => '2026-11-13', 'name' => 'X'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('tenant.vacations.holidays.destroy', [$this->tenant, $holiday]))->assertForbidden();

    expect($this->setting->refresh()->statutory_work_week)->toBe(WorkWeek::MonFri)
        ->and($type->refresh()->archived_at)->toBeNull()
        ->and(LeaveHoliday::query()->count())->toBe(1);
});

it('is closed to whoever cannot view vacations', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.vacations.settings.show', $this->tenant))->assertForbidden();
});

it('never reaches a type or a holiday of another company: 404 by url', function (): void {
    $type = LeaveType::factory()->create();
    $holiday = LeaveHoliday::factory()->create();

    $this->actingAs($this->hr)->put(route('tenant.vacations.types.update', [$this->tenant, $type]), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.vacations.types.archive', [$this->tenant, $type]))->assertNotFound();
    $this->actingAs($this->hr)->delete(route('tenant.vacations.holidays.destroy', [$this->tenant, $holiday]))->assertNotFound();

    expect($type->refresh()->name)->not->toBe('X')->and(LeaveHoliday::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('pages the types and the holidays on their own page names', function (): void {
    foreach (range(1, 20) as $number) {
        LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => "Tipo {$number}"]);
        LeaveHoliday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => sprintf('2027-02-%02d', $number), 'name' => "Festivo {$number}"]);
    }

    $props = leaveSettingsProps();

    expect($props['types']['per_page'])->toBe(15)->and($props['types']['total'])->toBe(25)
        ->and($props['holidays']['per_page'])->toBe(15)->and($props['holidays']['total'])->toBe(20)
        ->and($props['types']['next_page_url'])->toContain('types=2')
        ->and($props['holidays']['next_page_url'])->toContain('holidays=2');
});

it('opens the first time a company visits, before it has any setting', function (): void {
    $this->setting->delete();

    $props = leaveSettingsProps(['tab' => 'week']);

    expect($props['setting']['statutoryWorkWeek'])->toBe('mon_fri')->and($props['counts']['types'])->toBe(5);
});
