import { describe, expect, it } from 'vite-plus/test';

import {
    DOCUMENT_NUMERALS,
    dossierBlock,
    inapplicableText,
    isFixedPlace,
} from '@/lib/telework/dossier';

const NUMERALS = [
    '5.1',
    '5.1.1',
    '5.1.2',
    '5.2',
    '5.3',
    '5.4 y 7',
    '5.5',
    '5.5.1',
    '5.6',
    '5.7',
    '5.8',
    '5.9 y 8',
    '5.10',
    '5.11',
    '5.12',
    '5.13',
    '5.14',
];

describe('dossierBlock', () => {
    it('gives every row of table 10.3 its own evidence block, so none falls to a document it does not have', () => {
        for (const numeral of NUMERALS) {
            const block = dossierBlock(numeral);

            expect(block === 'document').toBe(
                DOCUMENT_NUMERALS.includes(numeral),
            );
        }
    });

    it('reads 5.1.2 as the fixed places in force', () => {
        expect(dossierBlock('5.1.2')).toBe('fixed');
    });
});

describe('isFixedPlace', () => {
    it('counts only the validated workplaces in force', () => {
        expect(isFixedPlace('valid')).toBe(true);
        expect(isFixedPlace('valid_with_plan')).toBe(true);
        expect(isFixedPlace('pending')).toBe(false);
        expect(isFixedPlace('not_valid')).toBe(false);
        expect(isFixedPlace('retired')).toBe(false);
    });
});

describe('inapplicableText', () => {
    it('never says Falta', () => {
        for (const numeral of NUMERALS) {
            expect(inapplicableText(numeral)).toMatch(/^No aplica: /);
            expect(inapplicableText(numeral)).not.toContain('Falta');
        }
    });

    it('speaks of notices for 5.11 and of people elsewhere', () => {
        expect(inapplicableText('5.11')).toContain('aviso');
        expect(inapplicableText('5.1')).toContain('teletrabaj');
    });
});
