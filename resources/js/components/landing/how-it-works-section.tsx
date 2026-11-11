import { m, useScroll } from 'motion/react';
import { useRef } from 'react';
import HowItWorksStep from '@/components/landing/how-it-works-step';
import InviteVisual from '@/components/landing/invite-visual';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { EASE_OUT, ONCE } from '@/components/landing/landing-motion';
import ResultsVisual from '@/components/landing/results-visual';
import Reveal from '@/components/landing/reveal';
import RosterVisual from '@/components/landing/roster-visual';
import { cn } from '@/lib/utils';

/**
 * Three steps joined by a rail: on a phone it runs down beside them and fills as you read; on a wide screen the steps
 * stand side by side under the title and the rail draws itself across their numbers.
 */
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
            <div className={cn(WRAP, 'grid gap-10 lg:gap-14')}>
                <Reveal className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between lg:gap-16">
                    <h2
                        id="como-funciona"
                        className={cn(TITLE, 'shrink-0 scroll-mt-24')}
                    >
                        Cómo funciona
                    </h2>
                    <p className={cn(LEAD, 'lg:max-w-[46ch] lg:pb-1.5')}>
                        Tú cargas a tu gente una vez. Lo demás lo lleva
                        Humetría, centro por centro.
                    </p>
                </Reveal>
                <div className="relative pl-[52px] lg:pl-0">
                    <span
                        aria-hidden
                        className="absolute top-2 bottom-2 left-[15px] w-0.5 rounded-full bg-border lg:hidden"
                    >
                        <m.span
                            style={{ scaleY: scrollYProgress }}
                            className="absolute inset-0 origin-top rounded-full bg-primary"
                        />
                    </span>
                    <span
                        aria-hidden
                        className="absolute top-5 right-0 left-5 hidden h-0.5 rounded-full bg-border lg:block"
                    >
                        <m.span
                            initial={{ transform: 'scaleX(0)' }}
                            whileInView={{ transform: 'scaleX(1)' }}
                            viewport={ONCE}
                            transition={{
                                duration: 1.2,
                                ease: EASE_OUT,
                                delay: 0.2,
                            }}
                            className="absolute inset-0 origin-left rounded-full bg-primary"
                        />
                    </span>
                    <ol
                        ref={steps}
                        className="grid gap-10 sm:gap-14 lg:grid-cols-3 lg:gap-8"
                    >
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
