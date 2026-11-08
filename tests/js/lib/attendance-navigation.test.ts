import { describe, expect, it } from 'vite-plus/test';

import { activeChild, complianceNavItems } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

const acme = (
    manageOrganization = true,
    manageMembers = true,
    role: 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints' = 'owner',
) => ({
    slug: 'acme',
    role,
    can: can(manageOrganization, manageMembers),
});

describe('the registro de jornada in the sidebar', () => {
    it('offers the registro de jornada to whoever may view it, with its corrections, after Ley Silla', () => {
        const tenant = acme(false, false, 'viewer');
        const items = complianceNavItems({
            ...tenant,
            can: { ...tenant.can, view_attendance: true },
        });
        const attendance = items.find((item) => item.title === 'Asistencia');

        expect(items.map((item) => item.title)).toEqual([
            'NOM-035',
            'NOM-037',
            'Ley Silla',
            'Asistencia',
        ]);
        expect(toUrl(attendance?.href ?? '')).toBe('/admin/acme/attendance');
        expect(
            (attendance?.children ?? []).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([
            ['Resumen', '/admin/acme/attendance'],
            ['Correcciones', '/admin/acme/attendance/corrections'],
            ['Dispositivos', '/admin/acme/attendance/devices'],
            ['Horarios', '/admin/acme/attendance/schedules'],
            ['Política', '/admin/acme/attendance/policy'],
        ]);
    });

    it('lights the summary on a center of the registro, and the corrections on theirs', () => {
        const tenant = acme(false, false, 'viewer');
        const children =
            complianceNavItems({
                ...tenant,
                can: { ...tenant.can, view_attendance: true },
            }).find((item) => item.title === 'Asistencia')?.children ?? [];

        expect(
            activeChild(children, '/admin/acme/attendance/centers/c-1')?.title,
        ).toBe('Resumen');
        expect(
            activeChild(children, '/admin/acme/attendance/corrections')?.title,
        ).toBe('Correcciones');
    });

    it('lights the devices, the schedules and the policy on their own pages', () => {
        const tenant = acme(false, false, 'viewer');
        const children =
            complianceNavItems({
                ...tenant,
                can: { ...tenant.can, view_attendance: true },
            }).find((item) => item.title === 'Asistencia')?.children ?? [];

        expect(
            activeChild(children, '/admin/acme/attendance/devices')?.title,
        ).toBe('Dispositivos');
        expect(
            activeChild(children, '/admin/acme/attendance/schedules')?.title,
        ).toBe('Horarios');
        expect(
            activeChild(children, '/admin/acme/attendance/policy')?.title,
        ).toBe('Política');
    });

    it('offers the report for the authority, lit on its page, only to whoever may manage the registro', () => {
        const owner = acme(true, true, 'owner');
        const children =
            complianceNavItems(owner).find(
                (item) => item.title === 'Asistencia',
            )?.children ?? [];
        const viewer = acme(false, false, 'viewer');

        expect(children.at(-1)?.title).toBe('Reporte para la autoridad');
        expect(toUrl(children.at(-1)?.href ?? '')).toBe(
            '/admin/acme/attendance/report',
        );
        expect(
            activeChild(children, '/admin/acme/attendance/report')?.title,
        ).toBe('Reporte para la autoridad');
        expect(
            (
                complianceNavItems({
                    ...viewer,
                    can: { ...viewer.can, view_attendance: true },
                }).find((item) => item.title === 'Asistencia')?.children ?? []
            ).map((item) => item.title),
        ).not.toContain('Reporte para la autoridad');
    });

    it('leaves it out for a role that cannot view it', () => {
        expect(
            complianceNavItems(acme(false, false, 'committee')).map(
                (item) => item.title,
            ),
        ).not.toContain('Asistencia');
    });
});
