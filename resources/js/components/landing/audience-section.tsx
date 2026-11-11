import { Briefcase, Building2, Layers } from 'lucide-react';
import {
    ICON_BOX,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';

const audiences = [
    {
        icon: Building2,
        title: 'Empresas',
        text: 'RH de una empresa con uno o varios centros de trabajo. Humetría le asigna a cada uno lo que le toca según su tamaño y su gente.',
    },
    {
        icon: Layers,
        title: 'Grupos',
        text: 'Un grupo con varias razones sociales, todas en una sola cuenta.',
    },
    {
        icon: Briefcase,
        title: 'Consultores',
        text: 'Atiendes a varias empresas, cada una con sus datos aparte y tu logo en los informes.',
    },
];

/** Who it is for: three cards, and a word for unions after them. */
export default function AudienceSection() {
    return (
        <section
            aria-labelledby="para-quien"
            className={cn('bg-sidebar', SECTION)}
        >
            <div className={cn(WRAP, 'flex flex-col gap-8 lg:gap-14')}>
                <h2 id="para-quien" className={TITLE}>
                    Hecha para quien lleva RH
                </h2>
                <div className="grid gap-3 md:grid-cols-3 md:gap-4">
                    {audiences.map(({ icon: Icon, title, text }, index) => (
                        <Reveal
                            key={title}
                            delay={index * 0.07}
                            className="flex"
                        >
                            <article className="grid flex-1 grid-cols-[48px_minmax(0,1fr)] content-start gap-x-4 gap-y-1.5 rounded-2xl border bg-card p-[22px] transition-[transform,border-color,box-shadow] duration-200 ease-out hover:-translate-y-0.5 hover:border-primary hover:shadow-raised md:grid-cols-1 md:gap-3.5 md:p-7">
                                <span
                                    className={`${ICON_BOX} row-span-2 size-12 rounded-[14px] md:row-span-1`}
                                >
                                    <Icon aria-hidden className="size-6" />
                                </span>
                                <h3 className="self-center text-xl leading-tight font-semibold tracking-[-0.02em]">
                                    {title}
                                </h3>
                                <p className="text-meta leading-normal text-muted-foreground">
                                    {text}
                                </p>
                            </article>
                        </Reveal>
                    ))}
                </div>
                <p
                    data-test="unions"
                    className="-mt-3 rounded-xl bg-accent px-[18px] py-4 text-meta lg:-mt-9"
                >
                    ¿Representas a un sindicato? Compensación tiene su propia
                    cuenta para tu lado de la mesa, con tus datos aparte de los
                    de la empresa.
                </p>
            </div>
        </section>
    );
}
