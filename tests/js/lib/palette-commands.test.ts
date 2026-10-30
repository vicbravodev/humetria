import { describe, expect, it, vi } from 'vite-plus/test';

import { paletteCommands } from '@/lib/palette-commands';
import { toUrl } from '@/lib/utils';

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
            tenant: {
                slug: 'acme',
                can: {
                    manage_organization: true,
                    manage_members: true,
                    manage_campaigns: true,
                    view_trauma_screenings: true,
                },
            },
            sidebarExpanded: true,
            impersonating: false,
            actions: spies,
        })
            .find(({ id }) => id === '/admin/acme')
            ?.run();

        expect(spies.visit).toHaveBeenCalledOnce();
        expect(spies.visit).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/admin/acme' }),
        );
    });

    it('offers the console pages only to the superadmin', () => {
        const labels = (isSuperadmin: boolean) =>
            paletteCommands({
                isSuperadmin,
                tenant: null,
                sidebarExpanded: true,
                impersonating: false,
                actions: actions(),
            }).map(({ label }) => label);

        expect(labels(true)).toContain('Empresas');
        expect(labels(false)).not.toContain('Empresas');
        expect(labels(false)).toContain('Panel');
        // Outside a company the superadmin has no panel: /admin takes them to Empresas.
        expect(labels(true)).not.toContain('Panel');
    });

    it('names the sidebar command after what it will do', () => {
        const label = (sidebarExpanded: boolean) =>
            paletteCommands({
                isSuperadmin: false,
                tenant: null,
                sidebarExpanded,
                impersonating: false,
                actions: actions(),
            }).find(({ id }) => id === 'sidebar')?.label;

        expect(label(true)).toBe('Contraer menú');
        expect(label(false)).toBe('Expandir menú');
    });

    it('runs each command through the action it stands for', () => {
        const spies = actions();
        const commands = paletteCommands({
            isSuperadmin: true,
            tenant: null,
            sidebarExpanded: true,
            impersonating: false,
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

describe('paletteCommands inside a company', () => {
    it('offers the actions a role may take inside a company, before the pages', () => {
        const actionsFor = (manage: boolean) =>
            paletteCommands({
                isSuperadmin: false,
                tenant: {
                    slug: 'acme',
                    can: {
                        manage_organization: manage,
                        manage_members: manage,
                        manage_campaigns: manage,
                        view_trauma_screenings: manage,
                    },
                },
                sidebarExpanded: true,
                impersonating: false,
                actions: actions(),
            });
        const commands = actionsFor(true);

        expect(
            commands
                .filter(({ group }) => group === 'Acciones')
                .map(({ label, id }) => [label, id]),
        ).toEqual([
            ['Nueva evaluación', 'new-evaluation'],
            ['Cargar empleados desde CSV', '/admin/acme/employees/import'],
        ]);
        expect(commands[0]?.group).toBe('Acciones');
        expect(
            actionsFor(false).filter(({ group }) => group === 'Acciones'),
        ).toEqual([]);
    });
});

describe('paletteCommands while impersonating', () => {
    it("opens Configuración at appearance while impersonating, since the owner's account is off limits", () => {
        const settingsUrl = (impersonating: boolean) => {
            const spies = actions();
            paletteCommands({
                isSuperadmin: true,
                tenant: null,
                sidebarExpanded: true,
                impersonating,
                actions: spies,
            })
                .find(({ id }) => id === 'settings')
                ?.run();

            return toUrl(spies.visit.mock.calls[0]?.[0] ?? '');
        };

        expect(settingsUrl(false)).toBe('/admin/settings/profile');
        expect(settingsUrl(true)).toBe('/admin/settings/appearance');
    });
});
