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
    });
});
