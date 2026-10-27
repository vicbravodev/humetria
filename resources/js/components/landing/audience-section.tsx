import { Briefcase, Building2, Check } from 'lucide-react';
import SectionHeading from '@/components/landing/section-heading';

const audiences = [
    {
        icon: Building2,
        title: 'Si la aplicas en tu empresa',
        text: 'Llevas la NOM-035 junto con todo lo demás de RH. Humetría hace el trabajo pesado para que tú te enfoques en actuar.',
        points: [
            'Las guías correctas para cada centro de trabajo',
            'El avance de cada campaña de un vistazo',
            'El diagnóstico y el informe, sin armar nada a mano',
        ],
    },
    {
        icon: Briefcase,
        title: 'Si eres consultor',
        text: 'Aplicas la norma a varios clientes y cada uno espera un trabajo impecable. Humetría te deja dar más servicio sin multiplicar el trabajo.',
        points: [
            'Todos tus clientes en una sola cuenta',
            'Tu logo en los informes que entregas',
            'Los datos de cada cliente, separados',
        ],
    },
] as const;

export default function AudienceSection() {
    return (
        <section
            aria-labelledby="para-quien"
            className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24"
        >
            <SectionHeading id="para-quien" title="Hecha para quien la aplica">
                Ya sea que cuides a la gente de tu empresa o acompañes a tus
                clientes, el camino es el mismo y igual de sencillo.
            </SectionHeading>

            <div className="mt-10 grid gap-6 md:grid-cols-2">
                {audiences.map(({ icon: Icon, title, text, points }) => (
                    <article
                        key={title}
                        className="flex flex-col rounded-lg border bg-card p-6 sm:p-8"
                    >
                        <span className="flex size-11 items-center justify-center rounded-lg bg-accent text-primary">
                            <Icon aria-hidden className="size-6" />
                        </span>
                        <h3 className="mt-5 text-xl leading-7 font-semibold">
                            {title}
                        </h3>
                        <p className="mt-2 text-muted-foreground">{text}</p>
                        <ul className="mt-6 space-y-3">
                            {points.map((point) => (
                                <li key={point} className="flex gap-3">
                                    <Check
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                    />
                                    {point}
                                </li>
                            ))}
                        </ul>
                    </article>
                ))}
            </div>
        </section>
    );
}
