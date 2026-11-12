import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { listeningNavItems, operationsNavItems } from '@/lib/tool-navigation';
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
    const listening = (modules: string[]) =>
        listeningNavItems(withModules(modules)).map((item) => item.title);
    const operations = (modules: string[]) =>
        operationsNavItems(withModules(modules)).map((item) => item.title);

    it('lists only the compliance tools whose module the company has', () => {
        expect(
            compliance(['attendance', 'nom035', 'nom037', 'seating']),
        ).toEqual(['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia']);
        expect(compliance(['nom035', 'seating'])).toEqual([
            'NOM-035',
            'Ley Silla',
        ]);
        expect(compliance(['attendance'])).toEqual(['Asistencia']);
        expect(compliance(['pulse'])).toEqual([]);
    });

    it('lists only the tools that listen whose module the company has', () => {
        expect(listening(['complaints', 'climate', 'pulse'])).toEqual([
            'Quejas',
            'Clima',
            'Pulso',
        ]);
        expect(listening(['climate'])).toEqual(['Clima']);
        expect(listening(['pulse'])).toEqual(['Pulso']);
        expect(listening(['complaints'])).toEqual(['Quejas']);
        expect(listening(['attendance'])).toEqual([]);
    });

    it('lists only the tools that order the operation whose module the company has, and keeps Compensación', () => {
        expect(operations(['vacations', 'org_chart'])).toEqual([
            'Vacaciones',
            'Organigrama',
            'Compensación',
        ]);
        expect(operations(['vacations'])).toEqual([
            'Vacaciones',
            'Compensación',
        ]);
        expect(operations(['org_chart'])).toEqual([
            'Organigrama',
            'Compensación',
        ]);
        expect(operations(['attendance'])).toEqual(['Compensación']);
    });

    it('lists everything when the page has no module list yet', () => {
        expect(complianceNavItems(owner).map((item) => item.title)).toEqual([
            'NOM-035',
            'NOM-037',
            'Ley Silla',
            'Asistencia',
        ]);
        expect(listeningNavItems(owner).map((item) => item.title)).toEqual([
            'Quejas',
            'Clima',
            'Pulso',
        ]);
        expect(operationsNavItems(owner).map((item) => item.title)).toEqual([
            'Vacaciones',
            'Organigrama',
            'Compensación',
        ]);
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
