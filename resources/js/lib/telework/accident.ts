import type { AccidentNotice } from '@/types';

const shortDate = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});

/** "27 feb", from a Y-m-d day. */
export function shortDay(day: string): string {
    return shortDate.format(new Date(`${day}T00:00:00Z`)).replace('.', '');
}

export type AccidentStage = 'open' | 'answered' | 'closed';

/** Where the follow-up of an accident stands (board V3-Aviso): nobody answered it, it is being followed, or it closed. */
export function accidentStage(notice: AccidentNotice): AccidentStage {
    if (notice.closedOn !== null) {
        return 'closed';
    }

    return notice.reviewedOn === null ? 'open' : 'answered';
}

/** The badge over the title: how long it has waited, or where it is. */
export function accidentBadge(notice: AccidentNotice): {
    text: string;
    tone: 'destructive' | 'success';
} {
    switch (accidentStage(notice)) {
        case 'open':
            return {
                text: `Sin revisar desde ${notice.waiting}`,
                tone: 'destructive',
            };
        case 'answered':
            return { text: 'En seguimiento', tone: 'success' };
        default:
            return { text: 'Cerrado', tone: 'success' };
    }
}

export type TimelineEntry = {
    key: string;
    title: string;
    detail?: string;
    /** What the person did is marked apart from what RH did; what is ahead is hollow. */
    tone: 'person' | 'done' | 'pending';
};

/** «Línea de tiempo»: when it happened, when the person told, what was done and its closing, or what is still pending. */
export function accidentTimeline(
    notice: AccidentNotice,
    person: string,
): TimelineEntry[] {
    return [
        {
            key: 'occurred',
            title: `${shortDay(notice.occurredOn)} · ocurrió`,
            tone: 'person',
        },
        {
            key: 'written',
            title: `${shortDay(notice.writtenOn)} · ${person} avisó`,
            detail: 'Por su portal',
            tone: 'person',
        },
        notice.reviewedOn === null
            ? {
                  key: 'answered',
                  title: 'Pendiente · registrar lo que se hizo',
                  tone: 'pending',
              }
            : {
                  key: 'answered',
                  title: `${shortDay(notice.reviewedOn)} · se registró lo que se hizo`,
                  tone: 'done',
              },
        notice.closedOn === null
            ? {
                  key: 'closed',
                  title: 'Pendiente · cerrar con evidencia',
                  tone: 'pending',
              }
            : {
                  key: 'closed',
                  title: `${shortDay(notice.closedOn)} · aviso cerrado`,
                  tone: 'done',
              },
    ];
}
