import { m } from 'motion/react';
import HeroHeadline from '@/components/landing/hero-headline';
import HeroVideo from '@/components/landing/hero-video';
import { ENTER } from '@/components/landing/landing-motion';
import { TOOLS } from '@/components/landing/landing-tools';
import { Button } from '@/components/ui/button';

/** Fades the parts under the headline in once it has landed. */
const after = (delay: number) => ({
    initial: { opacity: 0, transform: 'translateY(12px)' },
    animate: { opacity: 1, transform: 'translateY(0px)' },
    transition: { ...ENTER, delay },
});

export default function LandingHero() {
    return (
        <section
            aria-labelledby="hero-title"
            className="mx-auto grid max-w-[1240px] items-center gap-12 px-4 pt-12 pb-20 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-16 lg:pt-20 lg:pb-28"
        >
            <div className="flex flex-col gap-7">
                <HeroHeadline />
                <m.p
                    {...after(0.55)}
                    className="max-w-[46ch] text-lg leading-7 text-pretty text-muted-foreground lg:text-xl lg:leading-8"
                >
                    Humetría invita a tu gente, recolecta sus respuestas con la
                    confidencialidad que requiere cada herramienta y te entrega
                    lo que pide la autoridad: el diagnóstico, el plan y la
                    evidencia.
                </m.p>
                <m.div {...after(0.65)} className="flex flex-wrap gap-3">
                    <Button asChild size="lg">
                        <a href="#agenda-una-demo" data-test="hero-demo">
                            Agenda una demo
                        </a>
                    </Button>
                    <Button asChild size="lg" variant="outline">
                        <a href="#como-funciona">Ver cómo funciona</a>
                    </Button>
                </m.div>
                <m.p
                    {...after(0.75)}
                    data-test="hero-tools"
                    className="text-sm text-muted-foreground"
                >
                    {TOOLS.map((tool) => tool.name).join(' · ')}
                </m.p>
            </div>
            <HeroVideo />
        </section>
    );
}
