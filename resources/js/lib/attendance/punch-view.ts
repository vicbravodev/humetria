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

/**
 * Whether the page offers the QR reader: the company's policy says which marks ask for the code (`qrTypes`), and the person
 * needs it when one of the marks that may come next is among them. Nobody known, or no policy told: it is offered.
 */
export function needsQr(
    qrTypes: readonly string[] | null,
    next: readonly string[] | null,
): boolean {
    if (qrTypes === null || next === null) {
        return true;
    }

    return next.some((step) => qrTypes.includes(step));
}

/** A title or degree some people write before their name: «Ing.», «Lic.», «Dr.», «Dra.», «Mtro.», «Mtra.», «C.P.». */
const TITLE = /^(ing|lic|dr|dra|mtro|mtra|c\.p)\.?$/i;

/**
 * The first word of the name that is not a title: «Ana López» is greeted as «Ana», «Ing. Carlos Ruiz» as «Carlos». A name
 * that is only a title stays as it is: the greeting is never empty.
 */
export function firstName(name: string): string {
    const words = name.trim().split(/\s+/);

    return words.find((word) => !TITLE.test(word)) ?? words[0] ?? '';
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

const HOW: Record<string, string> = {
    strong: 'Con QR y ubicación',
    medium: 'Con QR o ubicación',
    basic: 'Con su llave',
    corrected: 'Corregida por RH',
};

/** In a field center there is no QR nor zone to prove: the position does. */
const FIELD_HOW: Record<string, string> = {
    strong: 'En campo, con su ubicación',
    medium: 'En campo, sin ubicación precisa',
};

/** Made without a connection: a QR proves it was not before it; with none, the time is the person's word within what the server checks. */
const OFFLINE_HOW: Record<string, string> = {
    medium: 'Sin conexión, no antes del QR del centro',
    basic: 'Sin conexión, hora declarada',
};

const HOW_BY_FLAG: Record<string, Record<string, string>> = {
    field: FIELD_HOW,
    offline: OFFLINE_HOW,
};

/**
 * How the mark was taken, in words and without a measure (the dots are for the authority's report); said for the field
 * or for a mark made without a connection when its flag is «field» or «offline». Null when the server named no level or
 * one the screen does not say.
 */
export function markHow(
    level: string | null,
    flag: string | null = null,
): string | null {
    return (HOW_BY_FLAG[flag ?? ''] ?? HOW)[level ?? ''] ?? null;
}

/** The city that names each zone of Mexico (Tenancy's MexicanTimezones); another zone by its own last part. */
const CITIES: Record<string, string> = {
    'America/Bahia_Banderas': 'Bahía de Banderas',
    'America/Cancun': 'Cancún',
    'America/Chihuahua': 'Chihuahua',
    'America/Ciudad_Juarez': 'Ciudad Juárez',
    'America/Hermosillo': 'Hermosillo',
    'America/Matamoros': 'Matamoros',
    'America/Mazatlan': 'Mazatlán',
    'America/Merida': 'Mérida',
    'America/Mexico_City': 'Ciudad de México',
    'America/Monterrey': 'Monterrey',
    'America/Ojinaga': 'Ojinaga',
    'America/Tijuana': 'Tijuana',
};

/** The time of a mark, with whose hour it is when it was taken in a zone that is not the person's center's. */
export function markClock(mark: { time: string; clock?: string }): string {
    if (mark.clock === undefined) {
        return mark.time;
    }

    const city =
        CITIES[mark.clock] ??
        (mark.clock.split('/').at(-1) ?? mark.clock).replaceAll('_', ' ');

    return `${mark.time} · hora de ${city}`;
}

/** How far the phone's clock is from the server's, in milliseconds: the page adds it to show the server's time. */
export function clockOffset(server: number, device: number): number {
    return server - device;
}
