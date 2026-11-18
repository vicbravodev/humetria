import { useReducedMotion } from 'motion/react';
import { useEffect, useState } from 'react';
import { WRAP } from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import ToolPagePhoneDemo from '@/components/landing/tool-page-phone-demo';
import ToolPageStep, { STEP_MS } from '@/components/landing/tool-page-step';
import { cn } from '@/lib/utils';

type Props = {
    steps: { title: string; text: string }[];
    /** The phone screen of the person, when the tool has one: it plays the steps beside them. */
    phone: string | null;
    phoneAlt: string | null;
};

/**
 * How the tool runs, in three steps. With a phone, the steps play one after another beside it (a bar fills under the one
 * playing) until the visitor picks one; with reduced motion they wait to be picked.
 */
export default function ToolPageSteps({ steps, phone, phoneAlt }: Props) {
    const still = useReducedMotion() === true;
    const [active, setActive] = useState(0);
    const [picked, setPicked] = useState(false);

    useEffect(() => {
        if (phone === null || still || picked) {
            return;
        }

        const timer = window.setInterval(
            () => setActive((step) => (step + 1) % steps.length),
            STEP_MS,
        );

        return () => window.clearInterval(timer);
    }, [phone, still, picked, steps.length]);

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
            <div
                className={cn(
                    'grid gap-10',
                    phone !== null &&
                        'lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-center',
                )}
            >
                <ol
                    className={cn(
                        'grid gap-3',
                        phone === null && 'gap-8 md:grid-cols-3',
                    )}
                >
                    {steps.map((step, index) => (
                        <ToolPageStep
                            key={step.title}
                            step={step}
                            index={index}
                            state={
                                phone === null
                                    ? 'static'
                                    : index === active
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
                {phone === null ? null : (
                    <Reveal delay={0.15} className="grid gap-3">
                        <ToolPagePhoneDemo
                            shot={phone}
                            alt={phoneAlt ?? ''}
                            step={active}
                            title={steps[active]?.title ?? ''}
                        />
                        <p className="text-center text-caption text-muted-foreground">
                            Así lo ve tu gente en su celular
                        </p>
                    </Reveal>
                )}
            </div>
        </section>
    );
}
