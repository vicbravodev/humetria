import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';

const audiences = [
    {
        title: 'Empresas',
        text: 'RH de una empresa con una o varias sucursales, plantas u oficinas (centros de trabajo). Cada centro recibe solo lo que le toca por su número de trabajadores.',
    },
    {
        title: 'Grupos de empresas',
        text: 'Un grupo con varias razones sociales, todas en una sola cuenta.',
    },
    {
        title: 'Consultores',
        text: 'Atiendes a varias empresas, cada una con sus datos aparte y tu logo en los informes.',
    },
];

/** Who it is for: three plain rows (who, then what changes for them), and a word for unions after them. */
export default function AudienceSection() {
    return (
        <section
            id="audiencia"
            aria-labelledby="para-quien"
            className={cn('border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'flex flex-col gap-8 lg:gap-12')}>
                <h2 id="para-quien" className={TITLE}>
                    Hecha para quien lleva RH
                </h2>
                <div className="divide-y border-y">
                    {audiences.map(({ title, text }, index) => (
                        <Reveal key={title} delay={index * 0.07}>
                            <article className="grid gap-2 py-6 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] md:gap-10 md:py-8">
                                <h3 className="text-2xl leading-tight font-semibold tracking-[-0.02em]">
                                    {title}
                                </h3>
                                <p className="max-w-[60ch] text-lg leading-7 text-muted-foreground">
                                    {text}
                                </p>
                            </article>
                        </Reveal>
                    ))}
                </div>
                <p
                    data-test="unions"
                    className="max-w-[60ch] text-meta text-muted-foreground"
                >
                    ¿Representas a un sindicato? Compensación tiene su propia
                    cuenta para tu lado de la mesa, con tus datos aparte de los
                    de la empresa.
                </p>
            </div>
        </section>
    );
}
