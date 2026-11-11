import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { can } from '../support/tenant-can';

const acme = (role: 'owner' | 'complaints' = 'owner') => ({
    slug: 'acme',
    role,
    can: can(true, true),
});

describe('the menu of a company with only some modules', () => {
    const owner = acme();
    const withModules = (modules: string[]) => ({ ...owner, modules });
    const compliance = (modules: string[]) =>
        complianceNavItems(withModules(modules)).map((item) => item.title);

    it('lists only the tools whose module the company has', () => {
        expect(
            compliance([
                'attendance',
                'nom035',
                'nom037',
                'seating',
                'complaints',
            ]),
        ).toEqual(['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia', 'Quejas']);
        expect(compliance(['nom035', 'seating'])).toEqual([
            'NOM-035',
            'Ley Silla',
        ]);
        expect(compliance(['attendance'])).toEqual(['Asistencia']);
        expect(compliance(['complaints'])).toEqual(['Quejas']);
        expect(compliance(['pulse'])).toEqual([]);
    });

    it('lists everything when the page has no module list yet', () => {
        expect(complianceNavItems(owner).map((item) => item.title)).toEqual([
            'NOM-035',
            'NOM-037',
            'Ley Silla',
            'Asistencia',
            'Vacaciones',
            'Quejas',
        ]);
    });

    it('lists Vacaciones for a company that has the vacations module', () => {
        expect(compliance(['vacations'])).toEqual(['Vacaciones']);
    });

    it('leaves the outside handler of complaints with no menu when the company has no complaints module', () => {
        const handler = { ...acme('complaints') };

        expect(
            mainNavItems({ ...handler, modules: ['complaints'] }, false),
        ).not.toEqual([]);
        expect(
            mainNavItems({ ...handler, modules: ['nom035'] }, false),
        ).toEqual([]);
    });
});
