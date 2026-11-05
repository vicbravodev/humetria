/** The two questions of «¿Te aplica la NOM-037?» (board V2-Aplica), and what each answer leads to. */
export type AppliesQuestion = 'outside' | 'share';

export type AppliesAnswer = 'yes' | 'no' | 'over' | 'occasional' | 'unsure';

export const QUESTIONS: {
    key: AppliesQuestion;
    title: string;
    hint: string;
    options: { value: AppliesAnswer; label: string }[];
}[] = [
    {
        key: 'outside',
        title: '¿Alguien de este centro trabaja desde su casa o desde otro lugar que elige, fuera de las instalaciones?',
        hint: 'Cuenta a cualquier persona, aunque sea solo una.',
        options: [
            { value: 'yes', label: 'Sí' },
            { value: 'no', label: 'No, todos trabajan en el centro' },
        ],
    },
    {
        key: 'share',
        title: '¿Esa persona trabaja fuera más del 40 % de su tiempo?',
        hint: 'Piensa en una semana normal.',
        options: [
            { value: 'over', label: 'Sí, más del 40 %' },
            { value: 'occasional', label: 'No, es de vez en cuando' },
            { value: 'unsure', label: 'No lo sé' },
        ],
    },
];

/**
 * What «Siguiente» does on a question: ask the next one, declare whether someone works away more than 40 % of their time,
 * or nothing while there is no answer (or it is «No lo sé», which shows how to tell instead).
 */
export function advance(
    answer: AppliesAnswer | null,
): { ask: AppliesQuestion } | { declare: boolean } | null {
    switch (answer) {
        case 'yes':
            return { ask: 'share' };
        case 'over':
            return { declare: true };
        case 'no':
        case 'occasional':
            return { declare: false };
        default:
            return null;
    }
}

/** The six stages of the route as the «Sí te aplica» screen lists them, with who does each and about how long. */
export const ROUTE_EFFORT = [
    { title: 'Define las reglas del teletrabajo', effort: '≈ 1 h · tú' },
    { title: 'Registra a quién teletrabaja y dónde', effort: '≈ 20 min · tú' },
    {
        title: 'Revisa que cada lugar sea seguro',
        effort: 'personas y Comisión',
    },
    { title: 'Entrega equipo y capacita', effort: '≈ 30 min · tú' },
    { title: 'Atiende lo que avisan las personas', effort: 'cuando llegue' },
    { title: 'Ten listo tu expediente', effort: 'automático' },
];
