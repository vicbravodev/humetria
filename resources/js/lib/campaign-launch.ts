import { describeDate } from '@/lib/calendar-date';

/** What launching does, said before it does it (C-03): how many emails go today and the day the evaluation closes. */
export function launchSummary(
    emails: number,
    closesOn: string,
    withoutEmail = 0,
): string {
    const count =
        emails === 1
            ? 'Se enviará 1 correo hoy.'
            : `Se enviarán ${emails} correos hoy.`;
    const missing =
        withoutEmail === 0
            ? ''
            : withoutEmail === 1
              ? ' 1 persona no lo recibirá por no tener correo: agrégalo en Empleados.'
              : ` ${withoutEmail} personas no lo recibirán por no tener correo: agrégalo en Empleados.`;
    const sent = `${count}${missing}`;
    const closes = describeDate(closesOn);

    return closes === undefined
        ? sent
        : `${sent} La evaluación cierra el ${closes}.`;
}
