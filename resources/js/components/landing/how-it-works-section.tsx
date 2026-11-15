import { useRef } from 'react';
import HowItWorksStep from '@/components/landing/how-it-works-step';
import InviteVisual from '@/components/landing/invite-visual';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import ResultsVisual from '@/components/landing/results-visual';
import Reveal from '@/components/landing/reveal';
import RosterVisual from '@/components/landing/roster-visual';
import { useScrollFill } from '@/hooks/use-scroll-fill';
import { cn } from '@/lib/utils';

/**
 * Three steps joined by a rail: on a phone it runs down beside them and fills as you read; on a wide screen the steps
 * stand side by side under the title and the rail draws itself across their numbers.
 */
export default function HowItWorksSection() {
    const steps = useRef<HTMLOListElement>(null);
    const fill = useScrollFill(steps);

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
                        <span
                            ref={fill}
                            className="absolute inset-0 origin-top [transform:scaleY(var(--p,0))] rounded-full bg-primary"
                        />
                    </span>
                    <span
                        aria-hidden
                        data-reveal="idle"
                        className="absolute top-5 right-0 left-5 hidden h-0.5 reveal-bar rounded-full bg-border [--reveal-duration:1200ms] enter-delay-200 lg:block"
                    >
                        <span className="absolute inset-0 rounded-full bg-primary" />
                    </span>
                    <ol
                        ref={steps}
                        className="grid gap-10 sm:gap-14 lg:grid-cols-3 lg:gap-8"
                    >
                        <HowItWorksStep
                            index={0}
                            title="Carga a tu gente"
                            text="Sube a las personas de cada sucursal, planta u oficina y Humetría define qué cuestionarios le tocan a cada centro."
                        >
                            <RosterVisual />
                        </HowItWorksStep>
                        <HowItWorksStep
                            index={1}
                            title="Humetría invita y recuerda"
                            text="Cada persona recibe su propio enlace, que sirve una sola vez, y contesta desde su celular sin que nadie vea sus respuestas."
                        >
                            <InviteVisual />
                        </HowItWorksStep>
                        <HowItWorksStep
                            index={2}
                            title="Recibe el diagnóstico"
                            text="Resultados por grupo, plan de acción y los documentos listos para la STPS."
                        >
                            <ResultsVisual />
                        </HowItWorksStep>
                    </ol>
                </div>
            </div>
        </section>
    );
}
