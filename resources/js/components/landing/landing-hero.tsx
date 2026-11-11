import { m } from 'motion/react';
import HeroFamilies from '@/components/landing/hero-families';
import HeroFilm from '@/components/landing/hero-film';
import HeroHeadline from '@/components/landing/hero-headline';
import { WRAP } from '@/components/landing/landing-layout';
import { ENTER } from '@/components/landing/landing-motion';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Fades the parts under the headline in once it has landed. */
const after = (delay: number) => ({
    initial: { opacity: 0, transform: 'translateY(12px)' },
    animate: { opacity: 1, transform: 'translateY(0px)' },
    transition: { ...ENTER, delay },
});

/** The promise and the film beside it; under both, the nine tools as three families. */
export default function LandingHero() {
    return (
        <section
            id="inicio"
            aria-labelledby="hero-title"
            className="relative overflow-clip"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-[-20%] top-[-10%] h-[80%] bg-[radial-gradient(60%_60%_at_70%_30%,color-mix(in_srgb,var(--primary)_14%,transparent),transparent_70%)]"
            />
            <div
                className={cn(
                    WRAP,
                    'relative grid gap-10 pt-8 pb-10 sm:pt-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)] lg:items-center lg:gap-x-16 lg:gap-y-14 lg:pt-14 lg:pb-16',
                )}
            >
                <div className="flex flex-col gap-6">
                    <HeroHeadline />
                    <m.p
                        {...after(0.55)}
                        className="max-w-[46ch] text-lg leading-7 text-pretty text-muted-foreground lg:text-xl lg:leading-8"
                    >
                        Humetría invita a tu gente, recolecta sus respuestas con
                        la confidencialidad que requiere cada herramienta y te
                        entrega lo que pide la autoridad: el diagnóstico, el
                        plan y la evidencia.
                    </m.p>
                    <m.div
                        {...after(0.65)}
                        className="grid gap-3 min-[520px]:flex min-[520px]:flex-wrap"
                    >
                        <Button
                            asChild
                            size="lg"
                            className="h-13 px-6 text-base"
                        >
                            <a href="#agenda-una-demo" data-test="hero-demo">
                                Agenda una demo
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-13 px-6 text-base"
                        >
                            <a href="#como-funciona">Ver cómo funciona</a>
                        </Button>
                    </m.div>
                </div>
                <m.div {...after(0.2)}>
                    <HeroFilm />
                </m.div>
                <m.div {...after(0.75)} className="lg:col-span-2">
                    <HeroFamilies />
                </m.div>
            </div>
        </section>
    );
}
