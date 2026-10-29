import { describe, expect, it, vi } from 'vite-plus/test';

import { mainNavItems } from '@/lib/app-navigation';
import { paletteCommands } from '@/lib/palette-commands';

const actions = () => ({
    visit: vi.fn(),
    setTheme: vi.fn(),
    toggleSidebar: vi.fn(),
});

describe('paletteCommands', () => {
    it("opens the current company's panel from Panel", () => {
        const spies = actions();
        paletteCommands({
            isSuperadmin: false,
            tenantSlug: 'acme',
            sidebarExpanded: true,
            actions: spies,
        })
            .find(({ id }) => id === '/admin/acme')
            ?.run();

        expect(spies.visit).toHaveBeenCalledOnce();
        expect(spies.visit).toHaveBeenCalledWith(mainNavItems('acme')[0]?.href);
    });

    it('offers the console pages only to the superadmin', () => {
        const labels = (isSuperadmin: boolean) =>
            paletteCommands({
                isSuperadmin,
                tenantSlug: null,
                sidebarExpanded: true,
                actions: actions(),
            }).map(({ label }) => label);

        expect(labels(true)).toContain('Empresas');
        expect(labels(false)).not.toContain('Empresas');
        expect(labels(false)).toContain('Panel');
    });

    it('names the sidebar command after what it will do', () => {
        const label = (sidebarExpanded: boolean) =>
            paletteCommands({
                isSuperadmin: false,
                tenantSlug: null,
                sidebarExpanded,
                actions: actions(),
            }).find(({ id }) => id === 'sidebar')?.label;

        expect(label(true)).toBe('Contraer menú');
        expect(label(false)).toBe('Expandir menú');
    });

    it('runs each command through the action it stands for', () => {
        const spies = actions();
        const commands = paletteCommands({
            isSuperadmin: true,
            tenantSlug: null,
            sidebarExpanded: true,
            actions: spies,
        });
        const run = (id: string) =>
            commands.find((command) => command.id === id)?.run();

        run('/admin/companies');
        run('settings');
        run('dark');
        run('light');
        run('system');
        run('sidebar');

        expect(spies.visit).toHaveBeenCalledTimes(2);
        expect(spies.setTheme.mock.calls).toEqual([
            ['dark'],
            ['light'],
            ['system'],
        ]);
        expect(spies.toggleSidebar).toHaveBeenCalledOnce();
    });
});
