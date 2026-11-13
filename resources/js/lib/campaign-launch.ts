import { describeDate } from '@/lib/calendar-date';

/** What launching does, said before it does it (C-03): how many emails go today and the day the evaluation closes. */
export function launchSummary(emails: number, closesOn: string): string {
    const sent =
        emails === 1
            ? 'Se enviará 1 correo hoy.'
            : `Se enviarán ${emails} correos hoy.`;
    const closes = describeDate(closesOn);

    return closes === undefined
        ? sent
        : `${sent} La evaluación cierra el ${closes}.`;
}
