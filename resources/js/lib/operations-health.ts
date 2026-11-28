import type { Service } from '@/lib/operations';
import type { HealthHours, HourTraffic, ServiceState } from '@/types';

/** One hour of a service: what it says to whoever points at it, and its tone. */
export type HourBar = { label: string; tone: 'idle' | 'ok' | 'warning' };

export type Alert = {
    title: string;
    detail: string;
    tone: 'warning' | 'destructive';
};

const SCHEDULER = 'Programador de tareas';

const clock = new Intl.DateTimeFormat('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: 'America/Mexico_City',
});

/** The 24 hours of a channel: idle without messages, a warning when the share rejected goes over the limit. */
export function hourBars(
    hours: HourTraffic[],
    channel: 'mail' | 'whatsapp',
    limit: number,
): HourBar[] {
    return hours.map((hour) => {
        const { total, rejected } = hour[channel];
        const at = clock.format(new Date(hour.startsAt));

        if (total === 0) {
            return { label: `${at} · sin mensajes`, tone: 'idle' };
        }

        return {
            label: `${at} · ${total} enviados, ${rejected} rechazados`,
            tone: (rejected / total) * 100 > limit ? 'warning' : 'ok',
        };
    });
}

const CHANNELS: Record<string, 'mail' | 'whatsapp'> = {
    'Correo (Resend)': 'mail',
    'WhatsApp (Meta)': 'whatsapp',
};

/** Mail and WhatsApp with their 24 hours; the other services keep no history, so they have no bars. */
export function withBars(
    list: Service[],
    { hours, rejectionLimit }: HealthHours,
): (Service & { bars: HourBar[] | null })[] {
    return list.map((service) => {
        const channel = CHANNELS[service.name];

        return {
            ...service,
            bars:
                channel === undefined
                    ? null
                    : hourBars(hours, channel, rejectionLimit),
        };
    });
}

/** The bars in one sentence, for whoever does not see their colors. */
export function barsSummary(bars: HourBar[], limit: number): string {
    const over = bars.filter(({ tone }) => tone === 'warning').length;

    if (over === 0) {
        return `Últimas 24 horas: ninguna hora pasó del ${limit} % de rechazos.`;
    }

    return over === 1
        ? `Últimas 24 horas: 1 hora pasó del ${limit} % de rechazos.`
        : `Últimas 24 horas: ${over} horas pasaron del ${limit} % de rechazos.`;
}

/** The services of the «Servicios» card, and the scheduler, which has a card of its own. */
export function splitServices(list: Service[]): {
    rows: Service[];
    scheduler: Service | undefined;
} {
    return {
        rows: list.filter(({ name }) => name !== SCHEDULER),
        scheduler: list.find(({ name }) => name === SCHEDULER),
    };
}

function alertTone(state: ServiceState): Alert['tone'] | null {
    if (state.variant === 'destructive' || state.variant === 'warning') {
        return state.variant;
    }

    return null;
}

/** What needs someone now: each service in trouble and the jobs that failed today. */
export function activeAlerts(list: Service[], failedToday: number): Alert[] {
    const alerts: Alert[] = [];

    for (const { name, detail, state } of list) {
        const tone = alertTone(state);

        if (tone !== null) {
            alerts.push({
                title: `${name}: ${state.label.toLowerCase()}`,
                detail,
                tone,
            });
        }
    }

    if (failedToday > 0) {
        alerts.push({
            title:
                failedToday === 1
                    ? '1 trabajo falló hoy'
                    : `${failedToday} trabajos fallaron hoy`,
            detail: 'Ya no se reintentan solos: se revisan en Horizon.',
            tone: 'warning',
        });
    }

    return alerts;
}
