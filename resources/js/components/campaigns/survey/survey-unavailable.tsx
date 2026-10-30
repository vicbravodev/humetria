import type { SurveyUnavailable } from '@/types';

const MESSAGES: Record<SurveyUnavailable, { title: string; body: string }> = {
    invalid: {
        title: 'Este enlace no es válido',
        body: 'Revise que lo haya abierto completo desde su correo. Si su empresa le envió uno nuevo, use el más reciente.',
    },
    closed: {
        title: 'Esta evaluación ya cerró',
        body: 'El periodo para contestar terminó. Si cree que es un error, comuníquelo al área de Recursos Humanos de su empresa.',
    },
    done: {
        title: 'Ya contestó todo lo que le correspondía',
        body: 'Gracias por su participación. Este enlace ya no se puede usar de nuevo.',
    },
    suspended: {
        title: 'La evaluación no está disponible por ahora',
        body: 'Intente más tarde o comuníquese con el área de Recursos Humanos de su empresa.',
    },
};

/** Why the link no longer opens the survey, in plain words and with what to do. */
export default function SurveyUnavailableNotice({
    reason,
}: {
    reason: SurveyUnavailable;
}) {
    const message = MESSAGES[reason];

    return (
        <section className="flex flex-col gap-3 rounded-xl border bg-card p-6">
            <h1 className="text-2xl leading-8 font-semibold">
                {message.title}
            </h1>
            <p className="text-lg leading-7 text-muted-foreground">
                {message.body}
            </p>
        </section>
    );
}
