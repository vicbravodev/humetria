import { Briefcase, Building2, Check, Network } from 'lucide-react';
import SectionHeading from '@/components/landing/section-heading';

const audiences = [
    {
        icon: Briefcase,
        title: 'Si eres consultor',
        text: 'Atiendes a varias empresas y cada una espera un trabajo impecable. Humetría te deja dar más servicio sin multiplicar el trabajo.',
        points: [
            'Cada empresa con su propio espacio',
            'Los datos de cada cliente, separados',
            'Tu logo en los informes que entregas',
        ],
    },
    {
        icon: Building2,
        title: 'Si tienes varios centros',
        text: 'Llevas a la gente de varios centros de trabajo junto con todo lo demás de RH. Humetría evalúa cada uno como pide la norma.',
        points: [
            'Lo que le toca a cada centro, según su tamaño',
            'El avance de cada centro de un vistazo',
            'El diagnóstico y la evidencia, sin armar nada a mano',
        ],
    },
    {
        icon: Network,
        title: 'Si llevas un grupo de empresas',
        text: 'Tu grupo opera con varias razones sociales y RH responde por todas. Humetría las reúne en la misma cuenta.',
        points: [
            'Todas tus razones sociales en una cuenta',
            'Cada una con su gente y sus centros',
            'Una sola forma de trabajar para todo el grupo',
        ],
    },
] as const;

export default function AudienceSection() {
    return (
        <section
            aria-labelledby="para-quien"
            className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24"
        >
            <SectionHeading id="para-quien" title="Hecha para quien lleva RH">
                Ya sea que cuides a la gente de tu empresa, de todo un grupo o
                de tus clientes, el camino es el mismo y igual de sencillo.
            </SectionHeading>

            <div className="mt-10 grid gap-6 lg:grid-cols-3">
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
