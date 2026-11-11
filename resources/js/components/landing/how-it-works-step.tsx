import { m } from 'motion/react';
import type { ReactNode } from 'react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

type Props = {
    index: number;
    title: string;
    text: string;
    children: ReactNode;
};

/** One step on the rail: its number, what happens and a small picture of what it leaves behind. */
export default function HowItWorksStep({
    index,
    title,
    text,
    children,
}: Props) {
    return (
        <li className="relative grid gap-[18px] lg:gap-6">
            <m.span
                aria-hidden
                initial={{ transform: 'scale(0.6)', opacity: 0 }}
                whileInView={{ transform: 'scale(1)', opacity: 1 }}
                viewport={{ once: true, amount: 0.6 }}
                transition={{ type: 'spring', duration: 0.5, bounce: 0.3 }}
                className="absolute top-0 -left-[52px] flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums ring-4 ring-background lg:-left-16 lg:size-10 lg:text-base"
            >
                {index + 1}
            </m.span>
            <div className="grid gap-1.5 lg:max-w-[44ch]">
                <h3 className="pt-0.5 text-[22px] leading-tight font-semibold tracking-[-0.02em] lg:text-[28px]">
                    {title}
                </h3>
                <p className="text-muted-foreground">{text}</p>
            </div>
            <m.div
                initial={{ opacity: 0, transform: 'translateY(20px)' }}
                whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
                viewport={ONCE}
                transition={{ ...ENTER, duration: 0.7 }}
                className="relative overflow-hidden card p-[18px] shadow-raised lg:max-w-[520px] lg:p-6"
            >
                {children}
            </m.div>
        </li>
    );
}
