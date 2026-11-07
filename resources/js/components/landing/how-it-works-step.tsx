import { m } from 'motion/react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

type Step = { title: string; text: string; detail: string };

/** One step of how it works: its number on the thread, what happens and the thing it leaves behind. */
export default function HowItWorksStep({
    step,
    index,
}: {
    step: Step;
    index: number;
}) {
    return (
        <m.li
            initial={{ opacity: 0, transform: 'translateY(16px)' }}
            whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
            viewport={ONCE}
            transition={{ ...ENTER, delay: 0.15 + index * 0.12 }}
            className="relative flex flex-col gap-3"
        >
            <span
                aria-hidden
                className="flex size-10 items-center justify-center rounded-full border-[1.5px] border-primary bg-sidebar font-semibold text-primary tabular-nums"
            >
                {index + 1}
            </span>
            <h3 className="mt-2 text-xl leading-7 font-semibold">
                {step.title}
            </h3>
            <p className="max-w-[36ch] text-muted-foreground">{step.text}</p>
            <p className="mt-1 self-start rounded-md border bg-card px-3 py-1.5 text-sm">
                {step.detail}
            </p>
        </m.li>
    );
}
