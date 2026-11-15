import { describe, expect, it } from 'vitest';
import { pendingNotice, policyHighlights } from '@/lib/disclosure-public';
import type { PolicyTextSection } from '@/types';

const section = (overrides: Partial<PolicyTextSection>): PolicyTextSection => ({
    key: 'commitments',
    heading: 'Compromisos',
    text: '',
    items: [],
    listStyle: null,
    ...overrides,
});

describe('policyHighlights', () => {
    it('takes the first commitments of a list, each as a sentence', () => {
        expect(
            policyHighlights([
                section({
                    items: ['Uno;', 'Dos, y', 'Tres.', 'Cuatro;'],
                    listStyle: 'numbers',
                }),
            ]),
        ).toEqual(['Uno.', 'Dos.', 'Tres.']);
    });

    it('takes the numbered paragraphs of a policy written with questions', () => {
        expect(
            policyHighlights([
                section({
                    text: 'En este centro se asumen:\n\n1. Uno;\n\n2. Dos, y\n\n3. Tres.',
                }),
            ]),
        ).toEqual(['Uno.', 'Dos.', 'Tres.']);
    });

    it('has none when nothing is numbered', () => {
        expect(policyHighlights([section({ text: 'Texto libre.' })])).toEqual(
            [],
        );
        expect(policyHighlights([])).toEqual([]);
    });
});

describe('pendingNotice', () => {
    it('says nothing when the policy and the results are both published', () => {
        expect(pendingNotice(true, true)).toBeNull();
    });

    it('names only what is still missing', () => {
        expect(pendingNotice(true, false)).toBe(
            'La empresa publicará aquí más información: los resultados de la evaluación y las medidas que toma.',
        );
        expect(pendingNotice(false, true)).toBe(
            'La empresa publicará aquí más información: la política de prevención.',
        );
        expect(pendingNotice(false, false)).toBe(
            'La empresa publicará aquí más información: la política de prevención, los resultados de la evaluación y las medidas que toma.',
        );
    });
});
