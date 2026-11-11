import { m, useScroll } from 'motion/react';
import { useRef } from 'react';
import HowItWorksStep from '@/components/landing/how-it-works-step';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import InviteVisual from '@/components/landing/invite-visual';
import ResultsVisual from '@/components/landing/results-visual';
import RosterVisual from '@/components/landing/roster-visual';
import { cn } from '@/lib/utils';

/** Three steps on a rail that fills as you read them; the title stays beside them on a wide screen. */
export default function HowItWorksSection() {
    const steps = useRef<HTMLOListElement>(null);
    const { scrollYProgress } = useScroll({
        target: steps,
        offset: ['start 70%', 'end 75%'],
    });

    return (
        <section
            id="seccion-como-funciona"
            aria-labelledby="como-funciona"
            className={SECTION}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-start lg:gap-24',
                )}
            >
                <Reveal className="flex flex-col gap-4 lg:sticky lg:top-28">
                    <h2
                        id="como-funciona"
                        className={cn(TITLE, 'scroll-mt-24')}
                    >
                        Cómo funciona
                    </h2>
                    <p className={LEAD}>
                        Tú cargas a tu gente una vez. Lo demás lo lleva
                        Humetría, centro por centro.
                    </p>
                </Reveal>
                <div className="relative pl-[52px] lg:pl-16">
                    <span
                        aria-hidden
                        className="absolute top-2 bottom-2 left-[15px] w-0.5 rounded-full bg-border lg:left-[19px]"
                    >
                        <m.span
                            style={{ scaleY: scrollYProgress }}
                            className="absolute inset-0 origin-top rounded-full bg-primary"
                        />
                    </span>
                    <ol ref={steps} className="grid gap-10 sm:gap-16 lg:gap-24">
                        <HowItWorksStep
                            index={0}
                            title="Carga a tu gente"
                            text="Sube a las personas de cada centro de trabajo y Humetría decide qué le toca a cada una."
                        >
                            <RosterVisual />
                        </HowItWorksStep>
                        <HowItWorksStep
                            index={1}
                            title="Humetría invita y recuerda"
                            text="Cada persona recibe su propio enlace, que sirve una sola vez, y contesta desde el celular con confianza."
                        >
                            <InviteVisual />
                        </HowItWorksStep>
                        <HowItWorksStep
                            index={2}
                            title="Recibe el diagnóstico"
                            text="Resultados por grupo, plan de acción y la evidencia lista para presentar ante la STPS."
                        >
                            <ResultsVisual />
                        </HowItWorksStep>
                    </ol>
                </div>
            </div>
        </section>
    );
}
