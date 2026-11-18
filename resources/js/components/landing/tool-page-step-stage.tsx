import { WhenMounted } from '@inertiajs/react';
import { AnimatePresence, m } from 'motion/react';
import { EASE_OUT } from '@/components/landing/landing-motion';
import { cn } from '@/lib/utils';
import type { ToolPageStep } from '@/types';

/**
 * The screen of the step being shown, in a stage of fixed size so nothing below it jumps: the panel in the page's theme, or
 * the phone of the person. Each one rises in as the step changes; reduced motion only fades (MotionConfig on the page).
 */
export default function ToolPageStepStage({
    step,
    index,
}: {
    step: ToolPageStep;
    index: number;
}) {
    const phone = step.shot.endsWith('-mobile');
    const frame = 'card h-auto max-h-full w-auto max-w-full shadow-raised';
    const screen = phone ? (
        <img
            src={`/images/tools/${step.shot}.webp`}
            alt={step.alt}
            width={585}
            height={1266}
            className="h-full max-h-full w-auto max-w-full rounded-[22px] border-[5px] border-foreground bg-foreground object-contain shadow-raised"
        />
    ) : (
        <>
            <img
                src={`/images/tools/${step.shot}-light.webp`}
                alt={step.alt}
                width={1520}
                height={1100}
                className={cn(frame, 'dark:hidden')}
            />
            <img
                src={`/images/tools/${step.shot}-dark.webp`}
                alt={step.alt}
                width={1520}
                height={1100}
                className={cn(frame, 'hidden dark:block')}
            />
        </>
    );
    const place =
        'absolute inset-4 flex items-center justify-center sm:inset-6';

    return (
        <div
            data-test="step-stage"
            className="relative aspect-[1520/1100] overflow-hidden rounded-[28px] bg-family-soft"
        >
            {/* The swap is motion, which writes a style the server must not print: Node prints the screen as it stands. */}
            <WhenMounted fallback={<div className={place}>{screen}</div>}>
                <AnimatePresence mode="wait" initial={false}>
                    <m.div
                        key={step.shot}
                        initial={{
                            opacity: 0,
                            y: 14,
                            scale: 0.98,
                            filter: 'blur(4px)',
                        }}
                        animate={{
                            opacity: 1,
                            y: 0,
                            scale: 1,
                            filter: 'blur(0px)',
                        }}
                        exit={{ opacity: 0, y: -8, scale: 0.99 }}
                        transition={{ duration: 0.35, ease: EASE_OUT }}
                        className={place}
                    >
                        {screen}
                    </m.div>
                </AnimatePresence>
            </WhenMounted>
            <span className="absolute top-3 left-3 z-10 rounded-full bg-family px-2.5 py-1 text-caption font-semibold text-background sm:top-4 sm:left-4">
                Paso {index + 1}
            </span>
        </div>
    );
}
