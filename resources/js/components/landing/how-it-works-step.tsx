import { m } from 'motion/react';
import type { ReactNode } from 'react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

type Props = {
    index: number;
    title: string;
    text: string;
    children: ReactNode;
};

/** One step: its number on the rail, what happens and a small picture of what it leaves behind. */
export default function HowItWorksStep({
    index,
    title,
    text,
    children,
}: Props) {
    return (
        <li className="relative flex flex-col gap-[18px] lg:gap-5">
            <m.span
                aria-hidden
                initial={{ transform: 'scale(0.6)', opacity: 0 }}
                whileInView={{ transform: 'scale(1)', opacity: 1 }}
                viewport={{ once: true, amount: 0.6 }}
                transition={{
                    type: 'spring',
                    duration: 0.5,
                    bounce: 0.3,
                    delay: index * 0.12,
                }}
                className="absolute top-0 -left-[52px] flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums ring-4 ring-background lg:static lg:size-10 lg:text-base"
            >
                {index + 1}
            </m.span>
            <div className="grid gap-1.5">
                <h3 className="pt-0.5 text-[22px] leading-tight font-semibold tracking-[-0.02em] lg:pt-0 lg:text-2xl">
                    {title}
                </h3>
                <p className="text-muted-foreground">{text}</p>
            </div>
            <m.div
                initial={{ opacity: 0, transform: 'translateY(20px)' }}
                whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
                viewport={ONCE}
                transition={{
                    ...ENTER,
                    duration: 0.7,
                    delay: 0.1 + index * 0.12,
                }}
                className="relative flex flex-1 flex-col justify-center overflow-hidden card p-[18px] shadow-raised lg:p-6"
            >
                {children}
            </m.div>
        </li>
    );
}
