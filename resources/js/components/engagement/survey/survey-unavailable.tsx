import { CirclePause, TimerOff, type LucideIcon } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';
import LinkUnavailable from '@/components/link-unavailable';
import type { SurveyUnavailable } from '@/types';

const MESSAGES: Record<
    Exclude<SurveyUnavailable, 'invalid' | 'done'>,
    { icon: LucideIcon; caption: string; title: string; body: string }
> = {
    closed: {
        icon: TimerOff,
        caption: 'Encuesta cerrada',
        title: 'Esta encuesta ya cerró',
        body: 'El periodo para contestar terminó. Si cree que es un error, comuníquelo al área de Recursos Humanos de su empresa.',
    },
    suspended: {
        icon: CirclePause,
        caption: 'Encuesta en pausa',
        title: 'La encuesta no está disponible por ahora',
        body: 'Intente más tarde o comuníquese con el área de Recursos Humanos de su empresa.',
    },
};

/** Why the link no longer opens the survey (pantalla: Enlace-Vencido), in plain words and with what to do. */
export default function SurveyUnavailableNotice({
    reason,
}: {
    reason: Exclude<SurveyUnavailable, 'done'>;
}) {
    if (reason === 'invalid') {
        return (
            <div className="flex flex-1 items-center justify-center py-8">
                <LinkUnavailable>
                    Revise que lo haya abierto completo desde su correo. Si su
                    empresa le envió uno nuevo, use el más reciente.
                </LinkUnavailable>
            </div>
        );
    }

    const message = MESSAGES[reason];

    return (
        <div className="flex flex-1 items-center justify-center py-8">
            <StatusMessage
                icon={message.icon}
                caption={message.caption}
                title={message.title}
            >
                {message.body}
            </StatusMessage>
        </div>
    );
}
