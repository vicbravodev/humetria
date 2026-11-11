import { weekRequests } from '@/lib/attendance/correction';
import type { WeekRequest } from '@/lib/attendance/correction';
import { sendSigned } from '@/lib/attendance/signed-request';
import type { SignDeps, SignedRequest } from '@/lib/attendance/signed-request';

/**
 * A mark as «Mi semana» shows it: its type, its local time, whether it fell on the next day, whether RH corrected it and
 * why (criterio 8.1).
 */
export type WeekMark = {
    id: string;
    type: string;
    time: string;
    nextDay: boolean;
    corrected: boolean;
    reason: string | null;
    /** How much the mark proves (`strong`, `medium`, `basic` or `corrected`); null when the server named none. */
    evidence: string | null;
};

export type WeekDay = {
    date: string;
    marks: WeekMark[];
    workedMinutes: number;
    scheduledMinutes: number;
    /** Of the minutes worked, those that were overtime: what the bar of the week draws apart. */
    overtimeMinutes: number;
    restDay: boolean;
    missingEnd: boolean;
    /** Away that day with approved vacations or a leave (never its type): not a day they missed. Its marks still count. */
    absence?: 'vacation' | 'leave' | null;
};

export type WeekSummary = {
    ordinaryMinutes: number;
    overtimeMinutes: number;
    doubleMinutes: number;
    tripleMinutes: number;
    restDayMinutes: number;
    flags: string[];
};

/** One week of the person as the server tells it after their device signed. Never a position. */
export type WeekView = {
    name: string;
    week: { start: string; end: string };
    status: string;
    answeredAt: string | null;
    disputeReason: string | null;
    /** The version of the week read; sent back with the answer, so it is about this week and not a changed one. */
    digest: string;
    /** Why it cannot be confirmed yet (`day_open`, `no_marks`); null when it can. */
    blocked: string | null;
    days: WeekDay[];
    requests: WeekRequest[];
    summary: WeekSummary;
    confirmUrl: string | null;
    disputeUrl: string | null;
    previousUrl: string;
    nextUrl: string | null;
};

export type WeekOutcome =
    | { kind: 'done'; week: WeekView }
    | { kind: 'failed'; reason: string; week?: WeekView };

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function text(value: unknown): string | null {
    return typeof value === 'string' ? value : null;
}

function mark(json: unknown): WeekMark[] {
    return isJson(json) &&
        typeof json['id'] === 'string' &&
        typeof json['type'] === 'string' &&
        typeof json['time'] === 'string'
        ? [
              {
                  id: json['id'],
                  type: json['type'],
                  time: json['time'],
                  nextDay: json['nextDay'] === true,
                  corrected: json['corrected'] === true,
                  reason: text(json['reason']),
                  evidence: text(json['evidence']),
              },
          ]
        : [];
}

/** Only vacations or leave: anything else is not an absence. */
function away(absence: unknown): Pick<WeekDay, 'absence'> {
    return absence === 'vacation' || absence === 'leave' ? { absence } : {};
}

function day(json: unknown): WeekDay[] {
    return isJson(json) &&
        typeof json['date'] === 'string' &&
        Array.isArray(json['marks']) &&
        typeof json['workedMinutes'] === 'number' &&
        typeof json['scheduledMinutes'] === 'number' &&
        typeof json['overtimeMinutes'] === 'number'
        ? [
              {
                  date: json['date'],
                  marks: json['marks'].flatMap(mark),
                  workedMinutes: json['workedMinutes'],
                  scheduledMinutes: json['scheduledMinutes'],
                  overtimeMinutes: json['overtimeMinutes'],
                  restDay: json['restDay'] === true,
                  missingEnd: json['missingEnd'] === true,
                  ...away(json['absence']),
              },
          ]
        : [];
}

function summary(json: unknown): WeekSummary | null {
    if (!isJson(json) || !Array.isArray(json['flags'])) {
        return null;
    }

    const ordinary = json['ordinaryMinutes'];
    const overtime = json['overtimeMinutes'];
    const double = json['doubleMinutes'];
    const triple = json['tripleMinutes'];
    const restDay = json['restDayMinutes'];

    return typeof ordinary === 'number' &&
        typeof overtime === 'number' &&
        typeof double === 'number' &&
        typeof triple === 'number' &&
        typeof restDay === 'number'
        ? {
              ordinaryMinutes: ordinary,
              overtimeMinutes: overtime,
              doubleMinutes: double,
              tripleMinutes: triple,
              restDayMinutes: restDay,
              flags: json['flags'].filter(
                  (flag: unknown) => typeof flag === 'string',
              ),
          }
        : null;
}

function period(json: unknown): WeekView['week'] | null {
    return isJson(json) &&
        typeof json['start'] === 'string' &&
        typeof json['end'] === 'string'
        ? { start: json['start'], end: json['end'] }
        : null;
}

/** The week in a server's answer; days or marks it cannot read are left out. Null when it is not a week. */
export function weekView(json: unknown): WeekView | null {
    if (!isJson(json) || !Array.isArray(json['days'])) {
        return null;
    }

    const name = text(json['name']);
    const status = text(json['status']);
    const previousUrl = text(json['previousUrl']);
    const digest = text(json['digest']);
    const dates = period(json['week']);
    const hours = summary(json['summary']);

    return name === null ||
        status === null ||
        previousUrl === null ||
        digest === null ||
        dates === null ||
        hours === null
        ? null
        : {
              name,
              week: dates,
              status,
              answeredAt: text(json['answeredAt']),
              disputeReason: text(json['disputeReason']),
              digest,
              blocked: text(json['blocked']),
              days: json['days'].flatMap(day),
              requests: weekRequests(json['requests']),
              summary: hours,
              confirmUrl: text(json['confirmUrl']),
              disputeUrl: text(json['disputeUrl']),
              previousUrl,
              nextUrl: text(json['nextUrl']),
          };
}

const BY_STATUS: Partial<Record<number, string>> = {
    404: 'not_verified',
    429: 'throttled',
};

async function failure(response: Response): Promise<WeekOutcome> {
    const body: unknown =
        response.status === 422 ? await response.json() : null;
    const reason = isJson(body)
        ? (text(body['reason']) ?? 'invalid')
        : BY_STATUS[response.status];
    const now = isJson(body) ? weekView(body['week']) : null;

    return {
        kind: 'failed',
        reason: reason ?? 'failed',
        ...(now === null ? {} : { week: now }),
    };
}

/**
 * Reads or answers a week: a challenge of the week's options, signed by the device (the person verifies with their
 * fingerprint, face or PIN), sent with `body` to `url`. Never throws.
 */
export async function sendWeek(
    request: SignedRequest,
    deps: SignDeps,
): Promise<WeekOutcome> {
    try {
        const response = await sendSigned(request, deps);

        if (typeof response === 'string') {
            return { kind: 'failed', reason: response };
        }

        if (!response.ok) {
            return failure(response);
        }

        const week = weekView(await response.json());

        return week === null
            ? { kind: 'failed', reason: 'failed' }
            : { kind: 'done', week };
    } catch {
        return { kind: 'failed', reason: 'failed' };
    }
}

const MESSAGES: Record<string, string> = {
    not_verified:
        'No se pudo verificar su dispositivo. Si aún no lo vincula, pida a Recursos Humanos su enlace para vincularlo.',
    cancelled:
        'No se confirmó su huella, su rostro o su PIN. Intente de nuevo.',
    throttled: 'Hubo demasiados intentos. Espere un minuto e intente de nuevo.',
    week_not_over:
        'Esta semana aún no termina. Podrá confirmarla u objetarla cuando termine el domingo.',
    week_disputed:
        'Usted objetó esta semana: Recursos Humanos la revisará antes de que pueda confirmarla.',
    already_disputed:
        'Ya objetó esta semana. Recursos Humanos revisará su motivo.',
    week_changed:
        'Su semana cambió desde que la abrió: Recursos Humanos corrigió sus marcas. Revísela de nuevo antes de responder.',
    day_open:
        'Su último turno de esta semana sigue abierto. Podrá confirmar su semana cuando marque su salida.',
    no_marks:
        'Esta semana no tiene marcas suyas. Si faltan, use «No estoy de acuerdo» para decirlo.',
    invalid:
        'Escriba qué no está bien en su semana, con al menos 10 caracteres.',
    unsupported:
        'Este navegador no puede usar llaves de acceso. Abra la página en el navegador de su teléfono.',
    future_time: 'La hora que propone aún no llega. Revise la hora y el día.',
    too_old:
        'Solo se pueden corregir marcas de los últimos 35 días. Hable con Recursos Humanos.',
    type_mismatch: 'Esa marca es de otro tipo. Intente de nuevo.',
    already_requested:
        'Ya pidió corregir esta marca. Recursos Humanos la está revisando.',
    too_many_pending:
        'Tiene 10 solicitudes esperando a Recursos Humanos. Espere a que las revisen antes de pedir otra.',
    reason_invalid:
        'Escriba su motivo en texto simple, con 500 caracteres a lo más.',
    invalid_request:
        'Escriba por qué pide la corrección, con al menos 10 caracteres, y la hora.',
};

/** What to tell the person when the week could not be read or answered. */
export function weekMessage(reason: string): string {
    return MESSAGES[reason] ?? 'No se pudo completar. Intente de nuevo.';
}
