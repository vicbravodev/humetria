import type { ReactNode } from 'react';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

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
            <span
                aria-hidden
                data-reveal="idle"
                data-reveal-amount="0.6"
                className={cn(
                    'absolute top-0 -left-[52px] flex size-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground tabular-nums ring-4 ring-background reveal-pop lg:static lg:size-10 lg:text-base',
                    enterDelay(index * 0.12),
                )}
            >
                {index + 1}
            </span>
            <div className="grid gap-1.5">
                <h3 className="pt-0.5 text-[22px] leading-tight font-semibold tracking-[-0.02em] lg:pt-0 lg:text-2xl">
                    {title}
                </h3>
                <p className="text-muted-foreground">{text}</p>
            </div>
            <div
                data-reveal="idle"
                className={cn(
                    'relative flex flex-1 flex-col justify-center overflow-hidden card p-[18px] shadow-raised reveal-rise-20 lg:p-6',
                    enterDelay(0.1 + index * 0.12),
                )}
            >
                {children}
            </div>
        </li>
    );
}
