import { describe, expect, it } from 'vitest';
import {
    agreementOf,
    agreementSections,
} from '@/lib/attendance/punch-agreement';

describe('agreementOf', () => {
    it('reads the agreement of an answer', () => {
        expect(
            agreementOf({ agreement: { version: 3, body: 'Texto' } }),
        ).toEqual({ version: 3, body: 'Texto' });
    });

    it('is null when the answer brings none it can read', () => {
        expect(agreementOf({ reason: 'outside_zone' })).toBeNull();
        expect(
            agreementOf({ agreement: { version: '3', body: 'x' } }),
        ).toBeNull();
        expect(agreementOf(null)).toBeNull();
    });
});

describe('agreementSections', () => {
    it('splits the agreement into its sections, each with its title', () => {
        expect(
            agreementSections(
                'Qué se guarda. La hora.\n\nQuién lo ve. Usted.\n\nSin título',
            ),
        ).toEqual([
            { title: 'Qué se guarda', text: 'La hora.' },
            { title: 'Quién lo ve', text: 'Usted.' },
            { title: '', text: 'Sin título' },
        ]);
    });
});
