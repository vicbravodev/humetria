import { describe, expect, it } from 'vitest';
import { roleSummary } from '@/lib/roles';

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
});
