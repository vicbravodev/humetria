import { markName } from '@/lib/attendance/next-step';

/** «Buenos días» until noon, «Buenas tardes» until 7 p.m., then «Buenas noches»; `hour` is the company's, 0 to 23. */
export function greeting(hour: number): string {
    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
}

type DayMark = { type: string; time: string };

export type DayStatus = {
    kind: 'ready' | 'working' | 'break' | 'done';
    title: string;
    text: string;
};

/** The first word of the name: «Ana López» is greeted as «Ana». */
export function firstName(name: string): string {
    return name.trim().split(/\s+/)[0] ?? '';
}

/**
 * Where the person's day stands, in a title and a line. From their marks when the server gave them (right after a mark); from
 * what may come next otherwise, never from marks: whoever marked before in this browser may not be who taps now.
 */
export function dayStatus(
    next: readonly string[],
    marks: readonly DayMark[] | null,
): DayStatus {
    const last = marks?.at(-1);

    switch (last?.type) {
        case 'start':
            return {
                kind: 'working',
                title: 'Está trabajando',
                text: `Entrada ${last.time}`,
            };
        case 'break_start':
            return {
                kind: 'break',
                title: 'Está en su comida',
                text: `Salió a comer ${last.time}`,
            };
        case 'break_end':
            return {
                kind: 'working',
                title: 'Está trabajando',
                text: `Regresó de comer ${last.time}`,
            };
        case 'end':
            return {
                kind: 'done',
                title: 'Jornada terminada',
                text: `Salida ${last.time}`,
            };
        default:
            return fromNext(next);
    }
}

function fromNext(next: readonly string[]): DayStatus {
    if (next[0] === 'break_end') {
        return {
            kind: 'break',
            title: 'Está en su comida',
            text: 'Marque su regreso cuando termine.',
        };
    }

    return next[0] === 'start'
        ? {
              kind: 'ready',
              title: 'Listo para su entrada',
              text: 'Marque cuando llegue a su centro de trabajo.',
          }
        : {
              kind: 'working',
              title: 'Está trabajando',
              text: 'Marque su salida a comer o su salida cuando corresponda.',
          };
}

const DONE: Record<string, string> = {
    start: 'Entrada registrada',
    break_start: 'Salida a comer registrada',
    break_end: 'Regreso de comer registrado',
    end: 'Salida registrada',
};

/** What the screen says of the mark just taken: «Entrada registrada». */
export function doneTitle(type: string): string {
    return DONE[type] ?? `${markName(type)} registrada`;
}

const EVIDENCE: Record<string, { dots: number; text: string }> = {
    strong: { dots: 3, text: 'Evidencia fuerte: QR y ubicación' },
    medium: { dots: 2, text: 'Evidencia media: QR o ubicación' },
    basic: { dots: 1, text: 'Evidencia básica: solo su llave' },
};

/** How much the mark proves, in words and in dots; null when the server named no level or one the screen does not say. */
export function evidenceText(
    level: string | null,
): { dots: number; text: string } | null {
    return EVIDENCE[level ?? ''] ?? null;
}
