import { describeDate } from '@/lib/calendar-date';

/** Who launching reaches, by channel: each person goes by one channel only (email or WhatsApp), or by none. */
export type LaunchReach = {
    emails: number;
    whatsapp: number;
    withoutContact: number;
};

const emailsText = (n: number): string =>
    n === 1 ? '1 correo' : `${n} correos`;

/** What goes today, by channel: «1 correo», «3 WhatsApp», or both. */
const sentText = ({ emails, whatsapp }: LaunchReach): string => {
    const parts = [
        ...(emails > 0 || whatsapp === 0 ? [emailsText(emails)] : []),
        ...(whatsapp > 0 ? [`${whatsapp} WhatsApp`] : []),
    ];
    const verb = emails + whatsapp === 1 ? 'Se enviará' : 'Se enviarán';

    return `${verb} ${parts.join(' y ')} hoy.`;
};

/** What launching does, said before it does it (C-03): what goes today through each channel and the day the evaluation closes. */
export function launchSummary(reach: LaunchReach, closesOn: string): string {
    const without = reach.withoutContact;
    const missing =
        without === 0
            ? ''
            : without === 1
              ? ' 1 persona no lo recibirá por no tener correo ni celular: agrégalo en Empleados.'
              : ` ${without} personas no lo recibirán por no tener correo ni celular: agrégalo en Empleados.`;
    const sent = `${sentText(reach)}${missing}`;
    const closes = describeDate(closesOn);

    return closes === undefined
        ? sent
        : `${sent} La evaluación cierra el ${closes}.`;
}
