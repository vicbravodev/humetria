import { describe, expect, it } from 'vitest';
import {
    requirementCount,
    requirementSince,
    summaryText,
} from '@/lib/seating/requirements';
import type { CenterRequirement } from '@/types';

const base: CenterRequirement = {
    key: 'r2',
    number: 'R2',
    label: 'Evaluación vigente',
    tab: 'people',
    state: 'missing',
    stateLabel: 'Falta',
    count: 3,
    since: '2026-10-02',
};

describe('requirementCount', () => {
    it('counts what is pending in the right number', () => {
        expect(requirementCount(base)).toBe('3 pendientes');
        expect(requirementCount({ ...base, count: 1 })).toBe('1 pendiente');
    });

    it('counts warnings apart', () => {
        expect(requirementCount({ ...base, state: 'warning', count: 2 })).toBe(
            '2 avisos',
        );
        expect(requirementCount({ ...base, state: 'warning', count: 1 })).toBe(
            '1 aviso',
        );
    });

    it('says nothing when it is met or there is nothing behind it', () => {
        expect(
            requirementCount({ ...base, state: 'met', count: 0 }),
        ).toBeNull();
        expect(requirementCount({ ...base, count: 0 })).toBeNull();
    });
});

describe('requirementSince', () => {
    it('writes the day the oldest thing has been waiting since', () => {
        expect(requirementSince(base)).toBe('Desde el 2 de octubre de 2026');
    });

    it('says nothing without a date or with one that is not a date', () => {
        expect(requirementSince({ ...base, since: null })).toBeNull();
        expect(requirementSince({ ...base, since: 'nunca' })).toBeNull();
    });
});

describe('summaryText', () => {
    it('says how many are met and what is left', () => {
        expect(summaryText({ met: 9, warning: 1, missing: 3 })).toBe(
            '9 de 13 cumplen · 1 con aviso · 3 por resolver',
        );
        expect(summaryText({ met: 10, warning: 2, missing: 1 })).toBe(
            '10 de 13 cumplen · 2 con avisos · 1 por resolver',
        );
    });

    it('says only how many are met when none is left', () => {
        expect(summaryText({ met: 13, warning: 0, missing: 0 })).toBe(
            '13 de 13 cumplen',
        );
    });
});
