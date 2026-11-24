import { describe, expect, it } from 'vitest';
import { roleSummary, rolesTab } from '@/lib/roles';

describe('roleSummary', () => {
    it('says in a few words what each role does', () => {
        expect(roleSummary('owner')).toBe(
            'administra la cuenta, sus usuarios y evaluaciones',
        );
        expect(roleSummary('hr')).toBe(
            'opera evaluaciones, centros y empleados',
        );
        expect(roleSummary('viewer')).toBe('ve resultados e informes');
        expect(roleSummary('committee')).toBe(
            'revisa y valida los lugares de teletrabajo',
        );
        expect(roleSummary('complaints')).toBe(
            'atiende, de forma confidencial, las quejas que se le asignan',
        );
    });

    it('speaks of the padrón, not of evaluations or centers, for a union', () => {
        expect(roleSummary('owner', true)).toBe(
            'administra la cuenta, sus usuarios y su padrón',
        );
        expect(roleSummary('hr', true)).toBe(
            'administra el padrón y la compensación',
        );
        expect(roleSummary('viewer', true)).toBe(roleSummary('viewer'));
    });

    it("says of a role of the company's own that it opens what was given to it", () => {
        expect(roleSummary('custom_3f2a')).toBe(
            'entra con los permisos que se le asignaron',
        );
        expect(roleSummary('custom_3f2a', true)).toBe(
            'entra con los permisos que se le asignaron',
        );
    });
});

describe('rolesTab', () => {
    it('opens the tab the address names', () => {
        expect(rolesTab('/admin/acme/roles?tab=plantillas', 3)).toBe(
            'plantillas',
        );
        expect(rolesTab('/admin/acme/roles?tab=propios', 0)).toBe('propios');
    });

    it("opens the company's roles once it has one, and the templates before", () => {
        expect(rolesTab('/admin/acme/roles', 1)).toBe('propios');
        expect(rolesTab('/admin/acme/roles?q=aud', 0)).toBe('propios');
        expect(rolesTab('/admin/acme/roles', 0)).toBe('plantillas');
        expect(rolesTab('/admin/acme/roles?tab=otra', 0)).toBe('plantillas');
    });
});
