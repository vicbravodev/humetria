import { useReducedMotion } from 'motion/react';
import { useEffect, useState } from 'react';
import { WRAP } from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import ToolPageStep, { STEP_MS } from '@/components/landing/tool-page-step';
import ToolPageStepStage from '@/components/landing/tool-page-step-stage';
import { cn } from '@/lib/utils';
import type { ToolPageStep as Step } from '@/types';

/**
 * How the tool runs, in three steps, each with its real screen beside it: where RH does it, the person's phone, the result.
 * They play one after another (a bar fills under the one playing) until the visitor picks one; with reduced motion they
 * wait to be picked.
 */
export default function ToolPageSteps({ steps }: { steps: Step[] }) {
    const still = useReducedMotion() === true;
    const [active, setActive] = useState(0);
    const [picked, setPicked] = useState(false);
    const shown = steps[active] ?? steps[0];

    useEffect(() => {
        if (still || picked) {
            return;
        }

        const timer = window.setInterval(
            () => setActive((step) => (step + 1) % steps.length),
            STEP_MS,
        );

        return () => window.clearInterval(timer);
    }, [still, picked, steps.length]);

    return (
        <section
            id="como-funciona"
            aria-labelledby="como-funciona-title"
            className={cn(WRAP, 'grid scroll-mt-16 gap-9 pt-16 lg:pt-22')}
        >
            <Reveal>
                <h2
                    id="como-funciona-title"
                    className="text-[30px] leading-tight font-semibold tracking-[-0.028em] sm:text-[40px]"
                >
                    Cómo funciona
                </h2>
            </Reveal>
            <div className="grid gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-center lg:gap-12">
                <ol className="grid gap-3">
                    {steps.map((step, index) => (
                        <ToolPageStep
                            key={step.title}
                            step={step}
                            index={index}
                            state={
                                index === active
                                    ? still || picked
                                        ? 'chosen'
                                        : 'playing'
                                    : 'idle'
                            }
                            onPick={() => {
                                setActive(index);
                                setPicked(true);
                            }}
                        />
                    ))}
                </ol>
                {shown === undefined ? null : (
                    <Reveal delay={0.15}>
                        <ToolPageStepStage step={shown} index={active} />
                    </Reveal>
                )}
            </div>
        </section>
    );
}
