import {
    Armchair,
    ClipboardList,
    FileText,
    LockKeyhole,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';

const groups = {
    privacy: {
        title: 'Cada quien sabe quién ve lo que contesta',
        points: [
            {
                icon: LockKeyhole,
                title: 'Respuestas sin nombre',
                text: 'Las respuestas de las Guías II y III de la NOM-035 y la encuesta de Ley Silla se guardan sin ninguna liga con la persona, su invitación ni su enlace.',
            },
            {
                icon: UsersRound,
                title: 'Resultados solo por grupo',
                text: 'Un grupo se muestra solo si reúne un mínimo de respuestas, también cuando filtras por área o centro.',
            },
            {
                icon: ShieldCheck,
                title: 'Lo identificado, solo para quien debe verlo',
                text: 'La Guía I de la NOM-035 y las listas de verificación de la NOM-037 sí identifican a la persona, porque la norma lo pide. Solo las consultan los roles autorizados.',
            },
        ],
    },
    norm: {
        title: 'Hecho con la norma en la mano',
        points: [
            {
                icon: FileText,
                title: 'NOM-035 · numeral 7.7',
                text: 'El informe lleva lo que pide el numeral: datos del centro, objetivo, actividades, método, resultados, conclusiones, acciones de intervención y responsable con cédula profesional.',
            },
            {
                icon: ClipboardList,
                title: 'NOM-037 · lista de verificación y política',
                text: 'El patrón valida la lista de verificación de cada lugar de teletrabajo y entrega por escrito la política, con descansos, desconexión y aviso de cambio de domicilio.',
            },
            {
                icon: Armchair,
                title: 'LFT · arts. 132, 133 y 423',
                text: 'Asientos con respaldo para trabajar y para descansar, nadie obligado a estar de pie toda la jornada, y los periodos de reposo en el reglamento interior.',
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
        <div
            data-test={`compliance-${group}`}
            className="rounded-lg border bg-card p-6 sm:p-8"
        >
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
