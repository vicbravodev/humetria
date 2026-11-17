<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/** Signs in the owner of Acme with one person, Ana López, whose day of joining is given, and returns her. */
function pickerEmployee(?string $hiredOn = null): Employee
{
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $area = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Producción']);

    return Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id, 'name' => 'Ana López', 'hired_on' => $hiredOn, 'seniority_on' => null]);
}

/** The value the form sends for a field. */
function sent(string $name): string
{
    return "() => document.querySelector('input[type=hidden][name={$name}]').value";
}

const HIRED_FIELD = '[data-slot="date-picker"]:has(#employee-hired_on)';

it('takes a day typed in digits, writes the slashes and sends it as Y-m-d', function (): void {
    $ana = pickerEmployee();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->type('#employee-hired_on', '07102018')
        ->assertValue('#employee-hired_on', '07/10/2018')
        ->assertScript(sent('hired_on'), '2018-10-07')
        ->click('[data-slot="sheet-content"] button[type="submit"]')
        ->assertSee('Empleado actualizado.')
        ->assertNoJavaScriptErrors();

    expect($ana->fresh()?->hired_on?->toDateString())->toBe('2018-10-07');
});

it('takes a pasted day in words with spaces around it and writes it as dd/mm/aaaa when the field is left', function (): void {
    pickerEmployee();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->fill('#employee-seniority_on', '  7 de octubre de 2018 ')
        ->assertScript(sent('seniority_on'), '2018-10-07')
        ->keys('#employee-seniority_on', 'Tab')
        ->assertValue('#employee-seniority_on', '07/10/2018');
});

it('clears an optional day, and sends it empty', function (): void {
    $ana = pickerEmployee('2018-10-07');

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->assertValue('#employee-hired_on', '07/10/2018')
        ->click(HIRED_FIELD.' button:has-text("Borrar la fecha")')
        ->assertValue('#employee-hired_on', '')
        ->assertScript(sent('hired_on'), '')
        ->assertScript('() => document.activeElement.id', 'employee-hired_on')
        ->click('[data-slot="sheet-content"] button[type="submit"]')
        ->assertSee('Empleado actualizado.');

    expect($ana->fresh()?->hired_on)->toBeNull();
});

it('says when the text is no date or falls after the last day allowed, and sends nothing', function (): void {
    pickerEmployee();
    // Two days ahead: the device's today may already be the server's tomorrow.
    $future = now('America/Mexico_City')->addDays(2);

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->fill('#employee-hired_on', '31/02/2018')
        ->keys('#employee-hired_on', 'Tab')
        ->assertSee('No es una fecha: escríbela como 07/10/2018.')
        ->assertScript(sent('hired_on'), '')
        ->assertAttribute('#employee-hired_on', 'aria-invalid', 'true')
        ->fill('#employee-hired_on', $future->format('d/m/Y'))
        ->keys('#employee-hired_on', 'Tab')
        ->assertSee('Elige una fecha hasta el ')
        ->assertScript(sent('hired_on'), '');
});

it('opens the calendar from the field without taking the focus, and closes it with Escape', function (): void {
    pickerEmployee();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->click('#employee-hired_on')
        ->assertVisible('[data-slot="popover-content"] [data-slot="calendar"]')
        ->assertScript('() => document.activeElement.id', 'employee-hired_on')
        ->keys('#employee-hired_on', 'Escape')
        ->assertMissing('[data-slot="popover-content"]')
        // Escape closes the calendar, not the panel around it.
        ->assertVisible('[data-slot="sheet-content"]')
        ->assertScript('() => document.activeElement.id', 'employee-hired_on');
});

it('picks a day with the keyboard from the calendar button, jumping to its year, and gives the focus back to the field', function (): void {
    pickerEmployee();

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->click(HIRED_FIELD.' button:has-text("Abrir el calendario")')
        ->assertScript('() => document.activeElement.closest("[data-day]") !== null', true)
        ->select('[data-slot="popover-content"] select[aria-label="Elegir el año"]', '2018')
        ->select('[data-slot="popover-content"] select[aria-label="Elegir el mes"]', '9')
        ->click('[data-day="2018-10-07"] button')
        ->assertMissing('[data-slot="popover-content"]')
        ->assertValue('#employee-hired_on', '07/10/2018')
        ->assertScript(sent('hired_on'), '2018-10-07')
        ->assertScript('() => document.activeElement.id', 'employee-hired_on')
        ->keys('#employee-hired_on', ['Alt+ArrowDown'])
        ->assertScript('() => document.activeElement.closest("[data-day]")?.dataset.day', '2018-10-07')
        ->keys('[data-day] button[tabindex="0"]', ['ArrowRight', 'Enter'])
        ->assertValue('#employee-hired_on', '08/10/2018')
        ->assertNoJavaScriptErrors();
});

it('opens the calendar as a sheet from the bottom on a phone, where the day can also be typed or cleared', function (bool $dark): void {
    $ana = pickerEmployee('2020-01-15');

    visitPage('organization/employees', '/admin/acme/employees', $dark, true)
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->click('#employee-hired_on')
        ->assertVisible('[data-slot="sheet-content"] [data-slot="calendar"]')
        ->assertMissing('[data-slot="popover-content"]')
        // The sheet sits on the bottom edge and fits the 375 px screen.
        ->assertScript('() => { const sheet = [...document.querySelectorAll("[data-slot=sheet-content]")].at(-1).getBoundingClientRect(); return Math.round(sheet.bottom) === window.innerHeight && sheet.width <= window.innerWidth; }', true)
        ->fill('#employee-hired_on-typed', '7 oct 2018')
        ->press('Listo')
        ->assertMissing('[data-slot="sheet-content"] [data-slot="calendar"]')
        ->assertValue('#employee-hired_on', '07/10/2018')
        ->assertScript(sent('hired_on'), '2018-10-07')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('[data-slot="sheet-content"] button[type="submit"]')
        ->assertSee('Empleado actualizado.')
        ->assertNoJavaScriptErrors();

    expect($ana->fresh()?->hired_on?->toDateString())->toBe('2018-10-07');
})->with(['light' => false, 'dark' => true]);

it('keeps the calendar day cells at 44 px on a phone', function (): void {
    pickerEmployee();

    visitPage('organization/employees', '/admin/acme/employees', false, true)
        ->click('[aria-label="Acciones de Ana López"]')->click('Editar')
        ->click('#employee-hired_on')
        ->assertScript('() => [...document.querySelectorAll("[data-slot=sheet-content] [data-day] button")].every((day) => day.getBoundingClientRect().height >= 44)', true);
});

it('closes the days after today for the last day of work, and offers no «Borrar» on a day that is required', function (): void {
    pickerEmployee('2026-01-10');
    $today = now('America/Mexico_City');

    visit('/admin/acme/employees')
        ->click('[aria-label="Acciones de Ana López"]')->click('Dar de baja')
        ->type('#terminated-on', $today->format('dmY'))
        ->assertMissing('[data-slot="date-picker"]:has(#terminated-on) button:has-text("Borrar la fecha")')
        ->click('[data-slot="date-picker"]:has(#terminated-on) button:has-text("Abrir el calendario")')
        ->assertScript('() => document.querySelector(\'[data-day="'.$today->copy()->addDay()->toDateString().'"] button\')?.disabled ?? true', true)
        ->keys('[data-slot="popover-content"]', 'Escape')
        ->assertScript(calendarClosed(), true)
        ->fill('#terminated-on', '09/01/2026')
        ->keys('#terminated-on', 'Tab')
        ->assertSee('Elige una fecha desde el 10/01/2026.');
});
