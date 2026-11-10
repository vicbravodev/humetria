import { describe, expect, it } from 'vite-plus/test';

import { answeredText, deadlineText } from '@/lib/engagement/panel';
import type { ClimateSurveyCard } from '@/types';

const card = (overrides: Partial<ClimateSurveyCard>): ClimateSurveyCard => ({
    id: 'id',
    name: 'Clima',
    status: 'active',
    statusLabel: 'En curso',
    closesOn: '2026-11-20',
    daysLeft: 3,
    invited: 60,
    answered: 18,
    rate: 30,
    ...overrides,
});

describe('deadlineText', () => {
    it('says when it closes and how many days remain', () => {
        expect(deadlineText(card({}))).toBe(
            'Cierra el 20 de noviembre de 2026 · quedan 3 días',
        );
    });

    it('calls the last day the last day', () => {
        expect(deadlineText(card({ daysLeft: 1 }))).toBe(
            'Cierra el 20 de noviembre de 2026 · último día',
        );
    });

    it('says only the day when the days left are not known', () => {
        expect(deadlineText(card({ daysLeft: null }))).toBe(
            'Cierra el 20 de noviembre de 2026',
        );
    });

    it('says a closed survey closed', () => {
        expect(deadlineText(card({ status: 'closed', daysLeft: null }))).toBe(
            'Cerró el 20 de noviembre de 2026',
        );
    });

    it('says nothing of a draft, which has no last day', () => {
        expect(deadlineText(card({ closesOn: null }))).toBeUndefined();
    });
});

describe('answeredText', () => {
    it('counts who answered', () => {
        expect(answeredText(card({}))).toBe('18 de 60 contestaron');
    });

    it('says a draft was not launched', () => {
        expect(answeredText(card({ invited: null, answered: null }))).toBe(
            'Todavía no se lanza: nadie ha recibido nada.',
        );
    });
});
