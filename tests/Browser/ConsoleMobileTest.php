<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

// Visual round 5, point 3: the console on a phone.

function consoleAuditEntries(int $count): void
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    $actor = User::factory()->create(['name' => 'Hugo RH', 'email' => 'hr@demo-nom035.test']);

    foreach (range(1, $count) as $index) {
        AuditEntry::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event' => 'campaign.launched',
            'metadata' => ['centro' => 'Planta Norte'],
            'created_at' => now()->subMinutes($index),
        ]);
    }
}

it('lists the audit trail as cards on a phone, with date, who and event in view and the email whole', function (): void {
    signInAsSuperadmin();
    consoleAuditEntries(3);

    visit('/admin/audit')->on()->mobile()
        ->assertSee('Campaña lanzada')
        // Each entry is a block of its own, not a row of a table squeezed into three columns.
        ->assertScript('() => getComputedStyle(document.querySelector(\'tbody tr\')).display', 'grid')
        // Date, event, who and the button are all inside the card and the card inside its frame, with no empty bands between them.
        ->assertScript('() => { const card = document.querySelector(\'tbody tr\'); const box = card.getBoundingClientRect(); const frame = card.closest(\'[data-slot="table-container"]\').getBoundingClientRect(); return box.right <= frame.right + 1 && box.height < 150 && [...card.querySelectorAll(\'td\')].filter((cell) => cell.innerText.trim() !== \'\' || cell.querySelector(\'button\')).every((cell) => cell.getBoundingClientRect().right <= box.right + 1); }', true)
        // The email wraps only if it must: here it fits in a single line.
        ->assertScript('() => { const email = [...document.querySelectorAll(\'tbody td span\')].find((span) => span.innerText.includes(\'@\')); return email.getClientRects().length; }', 1);
});

it('keeps the pagination of the audit trail clear of the bar at the foot of the phone, wherever the list is scrolled to', function (): void {
    signInAsSuperadmin();
    consoleAuditEntries(60);

    // The buttons never sit under the bar: at the top of the list (where a pinned pagination would be hidden by it) and at its end.
    $covered = <<<'JS'
        (where) => new Promise((resolve) => {
            const nav = document.querySelector('nav[aria-label="Páginas de la bitácora"]');
            if (where === 'end') { window.scrollTo(0, 1e6); document.querySelectorAll('main, [data-slot="sidebar-inset"]').forEach((box) => box.scrollTo(0, 1e6)); }
            setTimeout(() => {
                const bar = document.querySelector('nav[aria-label="Menú principal"]').getBoundingClientRect();
                resolve([...nav.querySelectorAll('a, button')].filter((button) => {
                    const box = button.getBoundingClientRect();
                    return box.bottom > bar.top && box.top < bar.bottom && box.bottom > 0 && box.top < window.innerHeight;
                }).length);
            }, 400);
        })
        JS;

    visit('/admin/audit')->on()->mobile()
        ->assertSee('Ver más antiguos')
        ->assertScript("() => ({$covered})('top')", 0)
        ->assertScript("() => ({$covered})('end')", 0);
});

it('keeps the buttons of a dialog on screen on a phone', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')->on()->mobile()
        ->click('@tenant-actions')
        ->click('Solo lectura')
        ->assertSee('Revisa qué cambia')
        ->assertScript('() => { const footer = document.querySelector(\'[data-slot="dialog-footer"]\').getBoundingClientRect(); const dialog = document.querySelector(\'[data-slot="dialog-content"]\').getBoundingClientRect(); return footer.bottom <= window.innerHeight && dialog.top >= 0 && dialog.bottom <= window.innerHeight; }', true);
});

it('reads a demo request on a phone without anything cut off', function (): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create(['name' => 'Ana López', 'message' => 'Queremos ver cómo se mide la NOM-035 en cuatro plantas.', 'created_at' => now()]);

    visit('/admin/demo-requests')->on()->mobile()
        ->assertSee('Ana López')
        ->assertScript(spilledOut(), '')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true);
});
