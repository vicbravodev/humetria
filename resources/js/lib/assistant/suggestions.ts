import type { PageContext } from '@/lib/assistant/chat-request';

const GENERAL = [
    '¿Qué me falta para estar al día?',
    '¿Cómo va la participación?',
    '¿Qué pendientes tengo primero?',
    'Explícame la NOM-037',
] as const;

const CAMPAIGN = [
    '¿Cómo va la participación?',
    '¿Dónde está el riesgo más alto?',
    '¿Qué me falta para cerrarla?',
] as const;

const WORK_CENTER = [
    '¿Cómo va este centro con la NOM-035?',
    '¿Qué le falta a este centro en la NOM-037?',
    '¿Qué pendientes tiene este centro?',
] as const;

/** What to offer as a first question, by the page the person has open. */
export function suggestionsFor(page: PageContext): readonly string[] {
    switch (page?.kind) {
        case 'campaign':
            return CAMPAIGN;
        case 'work_center':
            return WORK_CENTER;
        default:
            return GENERAL;
    }
}

const FOLLOW_UPS: Record<string, readonly string[]> = {
    progress: ['¿Cuándo cierra?', '¿Dónde está el riesgo más alto?'],
    risk_levels: [
        '¿Qué dice la norma que debo hacer?',
        '¿Qué pendientes tengo primero?',
    ],
    status_list: [
        '¿Qué pendientes tengo primero?',
        '¿Cómo va la participación?',
    ],
    list: [],
};

/** Two or three questions to follow an answer, by the cards it carried. */
export function followUpsFor(renders: readonly string[]): readonly string[] {
    const last = renders.at(-1);

    return last === undefined ? [] : (FOLLOW_UPS[last] ?? []);
}
