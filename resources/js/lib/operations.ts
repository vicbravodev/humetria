import type {
    ChannelTraffic,
    HorizonStatus,
    SystemHealthData,
    WhatsAppState,
} from '@/types';

/** The badge of a service: the variant carries the tone, the label always names the state. */
export type ServiceState = {
    variant: 'success' | 'warning' | 'destructive' | 'secondary';
    label: string;
};

export type Service = { name: string; detail: string; state: ServiceState };

const PAUSE_REASONS: Record<string, string> = {
    failure_rate: 'se pausó solo: demasiados rechazos',
    platform_cap: 'se pausó solo: llegó al tope de la plataforma',
};

/** «hace 3 min», «hace 2 h», «hace 4 días»: how long ago an ISO time was. */
export function since(iso: string, now: Date = new Date()): string {
    const minutes = Math.max(
        0,
        Math.floor((now.getTime() - new Date(iso).getTime()) / 60_000),
    );

    if (minutes < 1) {
        return 'hace menos de un minuto';
    }

    if (minutes < 60) {
        return `hace ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `hace ${hours} h`;
    }

    const days = Math.floor(hours / 24);

    return days === 1 ? 'hace 1 día' : `hace ${days} días`;
}

const HORIZON: Record<HorizonStatus, ServiceState> = {
    running: { variant: 'success', label: 'Operativo' },
    paused: { variant: 'warning', label: 'En pausa' },
    inactive: { variant: 'destructive', label: 'Detenido' },
    unreachable: { variant: 'destructive', label: 'No responde' },
};

function queuesService({
    status,
    waiting,
    queues,
}: SystemHealthData['queue']): Service {
    const longest = Math.max(0, ...queues.map((queue) => queue.wait));
    const detail =
        status === 'unreachable'
            ? 'Redis no contestó: no se sabe cuántos trabajos esperan.'
            : `${waiting} esperando · espera máxima ${longest} s`;

    return { name: 'Colas (Horizon)', detail, state: HORIZON[status] };
}

function schedulerService(
    { lastAt, late }: SystemHealthData['heartbeat'],
    now: Date,
): Service {
    const detail =
        lastAt === null
            ? 'No hay latido guardado.'
            : `Late cada 5 min · último ${since(lastAt, now)}`;

    return {
        name: 'Programador de tareas',
        detail,
        state: late
            ? { variant: 'destructive', label: 'Sin latido' }
            : { variant: 'success', label: 'Operativo' },
    };
}

function webhookService(
    name: string,
    lastAt: string | null,
    now: Date,
): Service {
    return lastAt === null
        ? {
              name,
              detail: 'El webhook no ha recibido eventos.',
              state: { variant: 'secondary', label: 'Sin eventos' },
          }
        : {
              name,
              detail: `Último evento ${since(lastAt, now)}`,
              state: { variant: 'success', label: 'Recibe eventos' },
          };
}

function whatsAppService(
    whatsApp: WhatsAppState,
    lastAt: string | null,
    now: Date,
): Service {
    const base = webhookService('WhatsApp (Meta)', lastAt, now);

    if (!whatsApp.enabled) {
        return {
            ...base,
            detail: 'La función está apagada (WHATSAPP_ENABLED).',
            state: { variant: 'secondary', label: 'Apagado' },
        };
    }

    if (whatsApp.paused) {
        const reason =
            whatsApp.pauseReason === null
                ? 'la pausó quien opera la plataforma'
                : (PAUSE_REASONS[whatsApp.pauseReason] ?? whatsApp.pauseReason);

        return {
            ...base,
            detail: `No se envía: ${reason}.`,
            state: { variant: 'warning', label: 'En pausa' },
        };
    }

    return base;
}

/** The services behind the platform, in the order the page lists them. */
export function services(
    health: SystemHealthData,
    now: Date = new Date(),
): Service[] {
    return [
        queuesService(health.queue),
        schedulerService(health.heartbeat, now),
        webhookService('Correo (Resend)', health.webhooks.resend, now),
        whatsAppService(health.whatsApp, health.webhooks.meta, now),
    ];
}

/** How many services are not working: what the header says next to the title. */
export function troubled(list: Service[]): number {
    return list.filter(
        ({ state }) =>
            state.variant === 'destructive' || state.variant === 'warning',
    ).length;
}

/** What the header says next to the title. */
export function troubleLine(issues: number): string {
    if (issues === 0) {
        return 'Todo operativo';
    }

    return issues === 1
        ? '1 servicio con problemas'
        : `${issues} servicios con problemas`;
}

/** The share delivered, «98.7 %», or a dash with nothing sent. */
export function deliveredShare(traffic: ChannelTraffic | undefined): string {
    if (traffic === undefined || traffic.total === 0) {
        return '—';
    }

    return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format((traffic.delivered / traffic.total) * 100)} %`;
}

export function trafficOf(
    list: ChannelTraffic[],
    channel: ChannelTraffic['channel'],
): ChannelTraffic | undefined {
    return list.find((traffic) => traffic.channel === channel);
}

/** A count in Mexican Spanish: «2,140». */
export function count(value: number): string {
    return new Intl.NumberFormat('es-MX').format(value);
}
