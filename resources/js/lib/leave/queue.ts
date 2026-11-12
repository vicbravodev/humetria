import { fromCalendarDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type {
    LeaveQueueFilters,
    LeaveQueueRequest,
    LeaveQueueTab,
    LeaveTeam,
} from '@/types';

const short = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
});
const list = new Intl.ListFormat('es', { type: 'conjunction' });

/** The query string of RH's queue: only what narrows it, so the first tab with no filters has a plain address. */
export function queueQuery(
    filters: LeaveQueueFilters,
): Partial<Record<keyof LeaveQueueFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(filters.tab === 'attend' ? {} : { tab: filters.tab }),
        ...(q === '' ? {} : { q }),
        ...(filters.center === null ? {} : { center: filters.center.value }),
        ...(filters.area === null ? {} : { area: filters.area.value }),
    };
}

/** What RH reads of the days of a request: whether the person had them, from which periods, or how many were missing. */
export function daysLine(
    request: Pick<LeaveQueueRequest, 'hadBalance' | 'periods' | 'shortBy'>,
): string {
    if (request.hadBalance === null) {
        return 'No usa días de vacaciones';
    }

    if (request.hadBalance) {
        return request.periods.length === 0
            ? 'Tenía días'
            : `Tenía días · del periodo ${request.periods.join(' y ')}`;
    }

    return `No tenía días · ${request.shortBy === 0 ? 'sin saldo al pedirla' : `le faltaban ${countOf(request.shortBy, 'día', 'días')}`}`;
}

/** «3 nov»: the day a request was asked, since when it waits. */
export function sinceLabel(day: string): string {
    const date = fromCalendarDate(day);

    return date === undefined ? '' : short.format(date).replace('.', '');
}

/** What RH reads of the team on the days of a request: nobody else is out, or who is (only that they are away). */
export function teamLine(team: LeaveTeam | null): string {
    if (team === null) {
        return '';
    }

    return team.names.length === 0
        ? 'Nadie más de su equipo está fuera'
        : `Fuera esos días: ${list.format(team.names)}`;
}

/** The line over the list that says how it is sorted: by urgency in what waits, by the most recent in what was answered. */
export function orderLine(tab: LeaveQueueTab): string {
    return `Ordenado por fecha de inicio: lo más ${tab === 'attend' || tab === 'waiting' ? 'próximo' : 'reciente'} arriba`;
}

/** The facts of a request besides its days, as [label, text]: only what exists, and the manager only once it was answered. */
export function requestDetails(
    request: Pick<
        LeaveQueueRequest,
        'status' | 'manager' | 'recordedByHr' | 'note' | 'rejectionReason'
    >,
): [string, string][] {
    const details: [string, string][] = [];

    if (request.status !== 'requested' && request.manager !== null) {
        details.push(['Le tocaba a', request.manager]);
    }

    if (request.recordedByHr) {
        details.push(['Registro', 'Lo registró RH a nombre de la persona']);
    }

    if (request.note !== null) {
        details.push(['Nota', request.note]);
    }

    if (request.rejectionReason !== null) {
        details.push(['Motivo del rechazo', request.rejectionReason]);
    }

    return details;
}

const EMPTY: Record<LeaveQueueTab, { title: string; text: string }> = {
    attend: {
        title: 'No hay solicitudes por atender.',
        text: 'Las que su jefe no responda a tiempo llegarán aquí.',
    },
    waiting: {
        title: 'Ninguna solicitud espera a un jefe.',
        text: 'Cuando alguien pida días, la solicitud llegará primero a su jefe.',
    },
    approved: {
        title: 'Aún no hay solicitudes aprobadas.',
        text: 'Las que se aprueben aparecerán aquí.',
    },
    rejected: {
        title: 'Aún no hay solicitudes rechazadas.',
        text: 'Las que se rechacen aparecerán aquí, con su motivo.',
    },
    cancelled: {
        title: 'Aún no hay solicitudes canceladas.',
        text: 'Las que se cancelen aparecerán aquí.',
    },
    taken: {
        title: 'Aún no hay solicitudes disfrutadas.',
        text: 'Las aprobadas cuyos días ya pasaron aparecerán aquí.',
    },
};

/** What the queue says when a tab has no rows: the title and what makes something appear. */
export function emptyQueue(tab: LeaveQueueTab): {
    title: string;
    text: string;
} {
    return EMPTY[tab];
}
