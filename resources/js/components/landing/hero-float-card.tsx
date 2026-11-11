import { m } from 'motion/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    detail: string;
    delay: number;
    className: string;
};

/** A note that floats over the hero film: what the film leads to, in one line. Decoration for the film's caption. */
export default function HeroFloatCard({
    icon: Icon,
    title,
    detail,
    delay,
    className,
}: Props) {
    return (
        <m.div
            aria-hidden
            initial={{ opacity: 0, transform: 'translateY(10px) scale(0.9)' }}
            animate={{ opacity: 1, transform: 'translateY(0px) scale(1)' }}
            transition={{ type: 'spring', duration: 0.6, bounce: 0.25, delay }}
            className={cn(
                'pointer-events-none absolute z-2 flex max-w-[72%] items-center gap-2.5 rounded-2xl border bg-card py-2.5 pr-4 pl-2.5 text-caption leading-tight shadow-float sm:py-3 sm:pr-5 sm:pl-3 sm:text-sm',
                className,
            )}
        >
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <Icon className="size-[15px]" />
            </span>
            <span>
                <b className="block text-sm font-semibold sm:text-meta">
                    {title}
                </b>
                <span className="text-muted-foreground">{detail}</span>
            </span>
        </m.div>
    );
}
