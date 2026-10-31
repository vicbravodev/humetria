import { describe, expect, it } from 'vite-plus/test';

import {
    headcountText,
    maritalText,
    pendingText,
    progressText,
    rowTone,
} from '@/lib/telework/center';

describe('rowTone', () => {
    it('paints each state of a row', () => {
        expect(rowTone('done')).toBe('success');
        expect(rowTone('partial')).toBe('warning');
        expect(rowTone('missing')).toBe('destructive');
        expect(rowTone('not_applicable')).toBe('secondary');
    });
});

describe('progressText', () => {
    it('counts the rows that apply', () => {
        expect(progressText({ done: 5, applicable: 16 })).toBe(
            '5 de 16 renglones',
        );
        expect(progressText({ done: 0, applicable: 1 })).toBe('0 de 1 renglón');
    });
});

describe('headcountText', () => {
    it('words people and aspirants', () => {
        expect(headcountText(1, 0)).toBe('1 persona');
        expect(headcountText(3, 1)).toBe('3 personas · 1 aspirante');
        expect(headcountText(0, 2)).toBe('0 personas · 2 aspirantes');
    });
});

describe('pendingText', () => {
    it('words the lists waiting for the Comisión', () => {
        expect(pendingText(0)).toBe('Sin listas por revisar');
        expect(pendingText(1)).toBe('1 lista por revisar');
        expect(pendingText(4)).toBe('4 listas por revisar');
    });
});

describe('maritalText', () => {
    it('words the estado civil, or a dash without one', () => {
        expect(maritalText('free_union')).toBe('Unión libre');
        expect(maritalText(null)).toBe('—');
        expect(maritalText(undefined)).toBe('—');
        expect(maritalText('other')).toBe('—');
    });
});
