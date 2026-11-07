import { m } from 'motion/react';
import { EASE_OUT, ONCE } from '@/components/landing/landing-motion';

/** One piece of the file: its check draws itself, one line after the other. */
export default function InspectionLine({
    text,
    tool,
    index,
}: {
    text: string;
    tool: string;
    index: number;
}) {
    const delay = 0.2 + index * 0.18;

    return (
        <m.li
            initial={{ opacity: 0, transform: 'translateY(10px)' }}
            whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
            viewport={ONCE}
            transition={{ duration: 0.5, ease: EASE_OUT, delay }}
            className="flex items-center gap-4 border-t border-primary-foreground/25 py-4 last:border-b dark:border-foreground/15"
        >
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-foreground text-primary dark:bg-primary dark:text-primary-foreground">
                <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
                    <m.path
                        d="M5 12.5l4.5 4.5L19 7.5"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2.5}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        initial={{ pathLength: 0 }}
                        whileInView={{ pathLength: 1 }}
                        viewport={ONCE}
                        transition={{
                            duration: 0.4,
                            ease: EASE_OUT,
                            delay: delay + 0.25,
                        }}
                    />
                </svg>
            </span>
            <span className="min-w-0 flex-1 text-lg leading-6 font-medium">
                {text}
            </span>
            <span className="hidden shrink-0 text-sm sm:block">{tool}</span>
        </m.li>
    );
}
