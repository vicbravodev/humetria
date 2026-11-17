import { formatDateText, formatMonthText } from '@/lib/date-text';
import type { TextLimits } from '@/lib/date-text';

/** Why a typed text is not taken: it is no value, or it falls before the first or after the last allowed. */
export type TextProblem = 'invalid' | 'early' | 'late';

/** The panel speaks in tú; the portal and the surveys, in usted. */
export type Voice = 'tu' | 'usted';

const examples = {
    date: { noun: 'una fecha', it: 'la', example: '07/10/2018' },
    month: { noun: 'un mes', it: 'lo', example: '10/2026' },
    time: { noun: 'una hora', it: 'la', example: '07:30' },
};

/** What the field says under it when the text left in it is not taken. */
export function textProblem(
    problem: TextProblem,
    {
        kind,
        limits = {},
        voice = 'tu',
    }: { kind: 'date' | 'month' | 'time'; limits?: TextLimits; voice?: Voice },
): string {
    const { noun, it, example } = examples[kind];

    if (problem === 'invalid') {
        const write = voice === 'tu' ? 'escríbe' : 'escríba';

        return `No es ${noun}: ${write}${it} como ${example}.`;
    }

    const pick = voice === 'tu' ? 'Elige' : 'Elija';
    const edge = (problem === 'early' ? limits.min : limits.max) ?? '';

    return `${pick} ${noun} ${problem === 'early' ? 'desde' : 'hasta'} ${writeEdge(edge, kind)}.`;
}

function writeEdge(edge: string, kind: 'date' | 'month' | 'time'): string {
    return kind === 'month'
        ? formatMonthText(edge)
        : `el ${formatDateText(edge)}`;
}
