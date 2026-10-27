import {
    ClipboardList,
    FileText,
    LockKeyhole,
    Scale,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';

const groups = {
    privacy: {
        title: 'Lo que contesta tu gente está protegido',
        points: [
            {
                icon: LockKeyhole,
                title: 'Respuestas sin nombre',
                text: 'Las respuestas de las Guías II y III se guardan sin ninguna liga con la persona, su invitación ni su enlace.',
            },
            {
                icon: UsersRound,
                title: 'Resultados solo por grupo',
                text: 'Un grupo se muestra solo si reúne un mínimo de respuestas, también cuando filtras por área o centro.',
            },
            {
                icon: ShieldCheck,
                title: 'La Guía I, solo para quien debe verla',
                text: 'Es la única que identifica a la persona, porque la norma pide canalizar a quien lo necesite. Solo la consultan los roles autorizados.',
            },
        ],
    },
    norm: {
        title: 'Lo que pide la NOM-035, en su lugar',
        points: [
            {
                icon: ClipboardList,
                title: 'Las guías que corresponden',
                text: 'Guía I para todos; Guía II de 16 a 50 trabajadores y Guía III con más de 50, según cada centro de trabajo.',
            },
            {
                icon: Scale,
                title: 'Muestra representativa',
                text: 'Con más de 50 trabajadores, el tamaño de la muestra sale de la fórmula de la norma y se elige al azar, en proporción por género.',
            },
            {
                icon: FileText,
                title: 'El informe del numeral 7.7',
                text: 'Datos del centro, objetivo, método, resultados, conclusiones y acciones de intervención, listos para la STPS.',
            },
        ],
    },
} as const;

export default function CompliancePoints({
    group,
}: {
    group: keyof typeof groups;
}) {
    const { title, points } = groups[group];

    return (
        <div className="rounded-lg border bg-card p-6 sm:p-8">
            <h3 className="text-xl leading-7 font-semibold">{title}</h3>
            <ul className="mt-6 space-y-6">
                {points.map(({ icon: Icon, title: point, text }) => (
                    <li key={point} className="flex gap-4">
                        <Icon
                            aria-hidden
                            className="mt-0.5 size-6 shrink-0 text-primary"
                        />
                        <div>
                            <p className="font-medium">{point}</p>
                            <p className="mt-1 text-muted-foreground">{text}</p>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
