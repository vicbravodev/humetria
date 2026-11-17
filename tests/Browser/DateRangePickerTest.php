<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

/** Signs in the superadmin with two entries in the trail: one from twenty days ago and one from early today (the same day in UTC). */
function rangeTrail(): void
{
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->create(['name' => 'Ana Pérez']);

    foreach ([now()->subDays(20), now('America/Mexico_City')->startOfDay()->addHour()] as $when) {
        AuditEntry::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event' => 'campaign.launched',
            'metadata' => [],
            'created_at' => $when,
        ]);
    }
}

/** Waits up to 3 s for the list to show this many entries, and says how many it shows. */
function rowsBecome(int $count): string
{
    return <<<JS
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                const rows = document.querySelectorAll('tbody tr').length;
                rows === {$count} || performance.now() - start > 3000 ? resolve(rows) : requestAnimationFrame(check);
            };
            check();
        })
        JS;
}

/** The value of a query parameter of the page's address. */
function queried(string $key): string
{
    return "() => new URLSearchParams(location.search).get('{$key}') ?? ''";
}

it('filters the trail by a first day typed in the field, once the field is left', function (): void {
    rangeTrail();
    $from = now('America/Mexico_City')->subDays(5);

    visit('/admin/audit')
        ->assertScript(rowsBecome(2), 2)
        ->type('#audit-period-from', $from->format('dmY'))
        ->assertValue('#audit-period-from', $from->format('d/m/Y'))
        // Nothing is asked of the server while typing.
        ->assertScript(queried('from'), '')
        ->keys('#audit-period-from', 'Tab')
        ->assertScript(rowsBecome(1), 1)
        ->assertScript(queried('from'), $from->toDateString())
        ->assertNoJavaScriptErrors();
});

it('picks the two ends of the period in one calendar and closes it', function (): void {
    rangeTrail();
    $today = now('America/Mexico_City');
    // Both ends inside the month the calendar opens on, and after the older entry.
    [$first, $last] = [$today->copy()->subDays(min(5, $today->day - 1)), $today];

    visit('/admin/audit')
        ->click('[data-slot="date-range-picker"] button:has-text("Abrir el calendario")')
        ->assertVisible('[data-slot="popover-content"] [data-slot="calendar"]')
        ->click('[data-day="'.$first->toDateString().'"] button')
        ->assertVisible('[data-slot="popover-content"] [data-slot="calendar"]')
        ->assertValue('#audit-period-from', $first->format('d/m/Y'))
        ->click('[data-day="'.$last->toDateString().'"] button')
        ->assertMissing('[data-slot="popover-content"]')
        ->assertValue('#audit-period-to', $last->format('d/m/Y'))
        ->assertScript(rowsBecome(1), 1)
        ->assertScript(queried('to'), $last->toDateString());
});

it('fills both ends from a shortcut of the period', function (): void {
    rangeTrail();
    $monday = now('America/Mexico_City')->startOfWeek();

    visit('/admin/audit')
        ->click('#audit-period-from')
        ->click('[data-slot="popover-content"] button:has-text("Semana pasada")')
        ->assertMissing('[data-slot="popover-content"]')
        ->assertValue('#audit-period-from', $monday->copy()->subWeek()->format('d/m/Y'))
        ->assertValue('#audit-period-to', $monday->copy()->subDay()->format('d/m/Y'))
        ->assertScript(queried('from'), $monday->copy()->subWeek()->toDateString());
});

it('closes the calendar of the period with Escape and keeps the focus in the field', function (): void {
    rangeTrail();

    visit('/admin/audit')
        ->click('#audit-period-to')
        ->assertVisible('[data-slot="popover-content"] [data-slot="calendar"]')
        ->keys('#audit-period-to', 'Escape')
        ->assertMissing('[data-slot="popover-content"]')
        ->assertScript('() => document.activeElement.id', 'audit-period-to');
});

it('opens the period as a sheet from the bottom on a phone, with its shortcuts, and fits the screen', function (bool $dark): void {
    rangeTrail();
    $monday = now('America/Mexico_City')->startOfWeek();

    visitPage('tenancy/audit', '/admin/audit', $dark, true)
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('#audit-period-from')
        ->assertVisible('[data-slot="sheet-content"] [data-slot="calendar"]')
        ->assertScript('() => [...document.querySelectorAll("[data-slot=sheet-content] [data-day] button")].every((day) => day.getBoundingClientRect().height >= 44)', true)
        ->click('[data-slot="sheet-content"] button:has-text("Semana pasada")')
        ->assertMissing('[data-slot="sheet-content"]')
        ->assertValue('#audit-period-from', $monday->copy()->subWeek()->format('d/m/Y'))
        ->assertNoJavaScriptErrors();
})->with(['light' => false, 'dark' => true]);
