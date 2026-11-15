import { isMarkType, markName } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';
import { dayTitle } from '@/lib/attendance/week-text';

/** What a person asks RH to correct: a mark of theirs (`markId`) or one they forgot, the time on their clock and why. */
export type CorrectionDraft = {
    markId: string | null;
    type: MarkType;
    /** The local day the time is on (Y-m-d) before `nextDay`. */
    date: string;
    /** HH:MM, as the person's clock reads it. */
    time: string;
    /** The time fell after midnight, on the day after `date`. */
    nextDay: boolean;
    reason: string;
};

/** The calendar day after a date (Y-m-d), whatever the browser's time zone. */
export function dayAfter(date: string): string {
    const [year = 0, month = 1, day = 1] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + 1))
        .toISOString()
        .slice(0, 10);
}

/** «Día: jueves 12 de noviembre»: the day a correction is about, said as the person reads it. */
export function correctionDay(date: string): string {
    return `Día: ${dayTitle(date).toLowerCase()}`;
}

/** The body the server takes: the local time as «Y-m-d H:i», and the week to give back when there is one. */
export function correctionBody(
    draft: CorrectionDraft,
    start: string | null,
): Record<string, unknown> {
    const date = draft.nextDay ? dayAfter(draft.date) : draft.date;

    return {
        mark_id: draft.markId,
        mark_type: draft.type,
        proposed_at: `${date} ${draft.time}`,
        reason: draft.reason.trim(),
        ...(start === null ? {} : { start }),
    };
}

/** A validation error has no reason of its own: tell the person what the form needs. */
export function correctionReason(reason: string): string {
    return reason === 'invalid' ? 'invalid_request' : reason;
}

const STATUS: Record<string, string> = {
    pending: 'En revisión',
    approved: 'Aprobada',
    rejected: 'Rechazada',
};

/** Where a request stands, in a word. */
export function requestStatus(status: string): string {
    return STATUS[status] ?? 'En revisión';
}

/**
 * The day (Y-m-d) and the time (HH:MM) of an instant in the company's time zone, whatever the phone's: what a mark refused
 * just now would have been.
 */
export function clockNow(
    now: Date,
    timeZone: string,
): { date: string; time: string } {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(now);
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((item) => item.type === type)?.value ?? '';

    return {
        date: `${part('year')}-${part('month')}-${part('day')}`,
        time: `${part('hour')}:${part('minute')}`,
    };
}

/** A request of the person to correct their week: what they asked, where it stands and RH's note. */
export type WeekRequest = {
    id: string;
    markId: string | null;
    type: string;
    date: string;
    time: string;
    status: string;
    note: string | null;
};

function text(value: unknown): string | null {
    return typeof value === 'string' ? value : null;
}

function request(json: unknown): WeekRequest[] {
    if (typeof json !== 'object' || json === null || Array.isArray(json)) {
        return [];
    }

    const read = (key: string): string | null =>
        key in json ? text(Reflect.get(json, key)) : null;
    const id = read('id');
    const type = read('type');
    const date = read('date');
    const time = read('time');
    const status = read('status');

    return id === null ||
        type === null ||
        date === null ||
        time === null ||
        status === null
        ? []
        : [
              {
                  id,
                  markId: read('markId'),
                  type,
                  date,
                  time,
                  status,
                  note: read('note'),
              },
          ];
}

/** The person's requests in a server's answer; those it cannot read are left out, and none is no list. */
export function weekRequests(json: unknown): WeekRequest[] {
    return Array.isArray(json) ? json.flatMap(request) : [];
}

/**
 * What the form asks about: a mark of the day (its type is known, its time to start from) or one the person forgot (none).
 */
export function correctionTarget(
    mark: { id: string; type: string; time: string } | null,
): {
    title: string;
    markId: string | null;
    type: MarkType | null;
    time: string;
} {
    if (mark === null) {
        return {
            title: 'Agregar una marca que faltó',
            markId: null,
            type: null,
            time: '',
        };
    }

    return {
        title: `Corregir ${markName(mark.type).toLowerCase()} de las ${mark.time}`,
        markId: mark.id,
        type: isMarkType(mark.type) ? mark.type : null,
        time: mark.time,
    };
}
