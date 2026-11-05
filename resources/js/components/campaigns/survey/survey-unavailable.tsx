import {
    CircleCheck,
    CirclePause,
    TimerOff,
    type LucideIcon,
} from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';
import LinkUnavailable from '@/components/link-unavailable';
import type { SurveyUnavailable } from '@/types';

const MESSAGES: Record<
    Exclude<SurveyUnavailable, 'invalid'>,
    { icon: LucideIcon; caption: string; title: string; body: string }
> = {
    closed: {
        icon: TimerOff,
        caption: 'Evaluación cerrada',
        title: 'Esta evaluación ya cerró',
        body: 'El periodo para contestar terminó. Si cree que es un error, comuníquelo al área de Recursos Humanos de su empresa.',
    },
    done: {
        icon: CircleCheck,
        caption: 'Enlace usado',
        title: 'Ya contestó todo lo que le correspondía',
        body: 'Gracias por su participación. Este enlace ya no se puede usar de nuevo.',
    },
    suspended: {
        icon: CirclePause,
        caption: 'Evaluación en pausa',
        title: 'La evaluación no está disponible por ahora',
        body: 'Intente más tarde o comuníquese con el área de Recursos Humanos de su empresa.',
    },
};

/** Why the link no longer opens the survey (pantalla: Enlace-Vencido), in plain words and with what to do. */
export default function SurveyUnavailableNotice({
    reason,
}: {
    reason: SurveyUnavailable;
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
