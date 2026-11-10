import { describe, expect, it } from 'vite-plus/test';

import {
    answeredText,
    deadlineText,
    pendingText,
    withoutLinkText,
} from '@/lib/engagement/panel';
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

describe('pendingText', () => {
    it('says how many are missing, in the singular too', () => {
        expect(pendingText(4)).toBe('Faltan 4 por contestar.');
        expect(pendingText(1)).toBe('Falta 1 por contestar.');
    });

    it('says nobody is missing', () => {
        expect(pendingText(0)).toBe('Nadie falta por contestar.');
    });
});

describe('withoutLinkText', () => {
    it('says how many have no email, in the singular too', () => {
        expect(withoutLinkText(2)).toBe(
            '2 personas sin correo no recibieron su enlace.',
        );
        expect(withoutLinkText(1)).toBe(
            '1 persona sin correo no recibió su enlace.',
        );
    });

    it('says nothing when everyone has one', () => {
        expect(withoutLinkText(0)).toBeUndefined();
    });
});
