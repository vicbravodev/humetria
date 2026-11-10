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
            isStaff: false,
            tenant: {
                slug: 'acme',
                role: 'owner',
                can: {
                    manage_organization: true,
                    manage_members: true,
                    manage_campaigns: true,
                    view_trauma_screenings: true,
                    manage_telework: true,
                    review_workplaces: true,
                    view_seating: true,
                    manage_seating: true,
                    assess_seating: true,
                    view_compensation: true,
                    manage_compensation: true,
                    view_interventions: true,
                    manage_interventions: true,
                    view_disclosure: true,
                    manage_disclosure: true,
                    use_assistant: true,
                    manage_complaint_channel: true,
                    handle_complaints: true,
                    view_attendance: false,
                    manage_attendance: false,
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
        const labels = (isStaff: boolean) =>
            paletteCommands({
                isStaff,
                tenant: null,
                sidebarExpanded: true,
                impersonating: false,
                actions: actions(),
            }).map(({ label }) => label);

        expect(labels(true)).toContain('Empresas');
        expect(labels(true)).toContain('Cuentas');
        expect(labels(false)).not.toContain('Empresas');
        expect(labels(false)).not.toContain('Cuentas');
        expect(labels(false)).toContain('Panel');
        // Outside a company the superadmin has no panel: /admin takes them to Empresas.
        expect(labels(true)).not.toContain('Panel');
    });

    it('names the sidebar command after what it will do', () => {
        const label = (sidebarExpanded: boolean) =>
            paletteCommands({
                isStaff: false,
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
            isStaff: true,
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
                isStaff: false,
                tenant: {
                    slug: 'acme',
                    role: 'owner',
                    can: {
                        manage_organization: manage,
                        manage_members: manage,
                        manage_campaigns: manage,
                        view_trauma_screenings: manage,
                        manage_telework: manage,
                        review_workplaces: manage,
                        view_seating: true,
                        manage_seating: manage,
                        assess_seating: manage,
                        view_compensation: manage,
                        manage_compensation: manage,
                        view_interventions: true,
                        manage_interventions: manage,
                        view_disclosure: true,
                        manage_disclosure: manage,
                        use_assistant: true,
                        manage_complaint_channel: manage,
                        handle_complaints: manage,
                        view_attendance: false,
                        manage_attendance: false,
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
                isStaff: true,
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

describe('paletteCommands inside a union', () => {
    it('offers loading employees but no evaluation, and no compliance page', () => {
        const commands = paletteCommands({
            isStaff: false,
            tenant: {
                slug: 'sindicato',
                type: 'union',
                role: 'owner',
                can: {
                    manage_organization: true,
                    manage_members: true,
                    manage_campaigns: true,
                    view_trauma_screenings: true,
                    manage_telework: true,
                    review_workplaces: true,
                    view_seating: true,
                    manage_seating: true,
                    assess_seating: true,
                    view_compensation: true,
                    manage_compensation: true,
                    view_interventions: true,
                    manage_interventions: true,
                    view_disclosure: true,
                    manage_disclosure: true,
                    use_assistant: true,
                    manage_complaint_channel: true,
                    handle_complaints: true,
                    view_attendance: false,
                    manage_attendance: false,
                },
            },
            sidebarExpanded: true,
            impersonating: false,
            actions: actions(),
        });
        const ids = commands.map(({ id }) => id);

        expect(ids).toContain('/admin/sindicato/employees/import');
        expect(ids).not.toContain('new-evaluation');
        expect(ids).not.toContain('/admin/sindicato/campaigns');
        expect(ids).not.toContain('/admin/sindicato/telework');
        expect(ids).not.toContain('/admin/sindicato/work-centers');
    });
});

describe('paletteCommands for Compensación', () => {
    const pagesOf = (type: 'company' | 'union', allowed: boolean) =>
        paletteCommands({
            isStaff: false,
            tenant: {
                slug: 'acme',
                type,
                role: 'owner',
                can: {
                    manage_organization: true,
                    manage_members: true,
                    manage_campaigns: true,
                    view_trauma_screenings: true,
                    manage_telework: true,
                    review_workplaces: true,
                    view_seating: true,
                    manage_seating: true,
                    assess_seating: true,
                    view_compensation: allowed,
                    manage_compensation: allowed,
                    view_interventions: true,
                    manage_interventions: true,
                    view_disclosure: true,
                    manage_disclosure: true,
                    use_assistant: true,
                    manage_complaint_channel: true,
                    handle_complaints: true,
                    view_attendance: false,
                    manage_attendance: false,
                },
            },
            sidebarExpanded: true,
            impersonating: false,
            actions: actions(),
        })
            .filter(({ group }) => group === 'Ir a')
            .map(({ label, id }) => [label, id]);

    it('lists it under Ir a for whoever may view it, in a company and in a union', () => {
        expect(pagesOf('company', true)).toContainEqual([
            'Compensación',
            '/admin/acme/compensation',
        ]);
        expect(pagesOf('union', true)).toContainEqual([
            'Compensación',
            '/admin/acme/compensation',
        ]);
    });

    it('leaves it out for a role that cannot view it', () => {
        expect(pagesOf('company', false).map(([label]) => label)).not.toContain(
            'Compensación',
        );
    });
});
