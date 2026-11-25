import { count, since } from '@/lib/operations';
import type {
    ChannelTraffic,
    MessagingConnection,
    ServiceState,
    TemplateTraffic,
} from '@/types';

const PAUSE_REASONS: Record<string, string> = {
    failure_rate: 'Demasiados rechazos',
    platform_cap: 'Llegó al tope de la plataforma',
};

// What each template in code says, in Spanish: its key is Meta's name and carries English words.
const TEMPLATES: Record<string, string> = {
    survey_invite: 'Invitación a la evaluación NOM-035',
    seating_invite: 'Invitación a la encuesta de Ley Silla',
    engagement_invite: 'Invitación a la encuesta de clima',
    survey_reminder: 'Recordatorio de la evaluación NOM-035',
    engagement_reminder: 'Recordatorio de la encuesta de clima',
    leave_decision: 'Respuesta a una solicitud',
    leave_to_decide: 'Solicitud por responder',
    policy_to_confirm: 'Política por confirmar',
    engagement_feedback: 'Plan tras la encuesta de clima',
    seating_risks: 'Resultado de la evaluación del puesto',
    seating_questions: 'Preguntas sobre el trabajo de pie',
    telework_invite: 'Lista de teletrabajo por completar',
    telework_reminder: 'Pendientes de teletrabajo',
    telework_answered: 'Respuesta a un aviso de teletrabajo',
    support_waiting: 'Aviso en el portal',
    attendance_correction: 'Respuesta a una corrección de asistencia',
    worker_invite: 'Invitación al portal',
    access_link: 'Enlace para entrar al portal',
    key_registered: 'Llave de acceso nueva',
    proof_code: 'Clave de verificación',
};

/** «Invitación a la evaluación NOM-035 · v1»; a template not named here shows its key. */
export function templateLabel(key: string): string {
    const match = /^(.+)_v(\d+)$/.exec(key);
    const name = match?.[1] === undefined ? undefined : TEMPLATES[match[1]];

    return name === undefined ? key : `${name} · v${match?.[2] ?? ''}`;
}

export function categoryLabel(category: TemplateTraffic['category']): string {
    return category === 'AUTHENTICATION' ? 'Autenticación' : 'Utilidad';
}

/** Whether WhatsApp sends now: off, paused or on. */
export function connectionState(connection: MessagingConnection): ServiceState {
    if (!connection.enabled) {
        return { variant: 'secondary', label: 'Apagado' };
    }

    return connection.paused
        ? { variant: 'warning', label: 'En pausa' }
        : { variant: 'success', label: 'Enviando' };
}

/** The facts of the connection, one row each: why it is paused and since when, and Meta's last event. */
export function connectionFacts(
    connection: MessagingConnection,
    now: Date = new Date(),
): { label: string; value: string }[] {
    const facts = [];

    if (connection.paused) {
        facts.push({
            label: 'Motivo de la pausa',
            value:
                connection.pauseReason === null
                    ? 'La pausó quien opera la plataforma'
                    : (PAUSE_REASONS[connection.pauseReason] ??
                      connection.pauseReason),
        });
    }

    if (connection.pausedSince !== null) {
        facts.push({
            label: 'En pausa desde',
            value: since(connection.pausedSince, now),
        });
    }

    facts.push({
        label: 'Último evento de Meta',
        value:
            connection.lastEventAt === null
                ? 'Ninguno'
                : since(connection.lastEventAt, now),
    });

    return facts;
}

/** From sent to read, for the WhatsApp of the day: each step with its share of what was sent. */
export function funnel(
    traffic: ChannelTraffic | undefined,
): { label: string; value: string; percent: number }[] {
    const total = traffic?.total ?? 0;
    const share = (part: number) => (total === 0 ? 0 : (part / total) * 100);
    const step = (label: string, part: number) => ({
        label,
        value: count(part),
        percent: share(part),
    });

    return [
        step('Enviados', total),
        step('Entregados', traffic?.delivered ?? 0),
        step('Leídos', traffic?.read ?? 0),
        step('Rechazados', traffic?.rejected ?? 0),
    ];
}

/** Rejected messages of every channel in a range. */
export function rejected(list: ChannelTraffic[]): number {
    return list.reduce((sum, traffic) => sum + traffic.rejected, 0);
}
