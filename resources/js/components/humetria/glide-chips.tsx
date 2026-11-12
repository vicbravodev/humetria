import { AnimatePresence, m } from 'motion/react';
import { cn } from '@/lib/utils';

const GLIDE = { type: 'spring', duration: 0.45, bounce: 0.12 } as const;

/**
 * The chips behind one item of a list of links (the landing's sections and tools, the phone's menu): the brand-colored one marks
 * where the reader is and the faint one follows the pointer. Each glides from item to item (shared layout), so the eye
 * sees it travel instead of jump; the faint one fades as the pointer leaves the list.
 */
export default function GlideChips({
    group,
    current,
    hovered,
    className,
}: {
    /** Names the list, so its chips only glide within it. */
    group: string;
    current: boolean;
    hovered: boolean;
    className: string;
}) {
    return (
        <>
            <AnimatePresence>
                {hovered && !current && (
                    <m.span
                        key="hover"
                        aria-hidden
                        data-chip="hover"
                        layoutId={`${group}-hover`}
                        transition={GLIDE}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className={cn(
                            'absolute inset-0 bg-foreground/[0.06]',
                            className,
                        )}
                    />
                )}
            </AnimatePresence>
            {current && (
                <m.span
                    aria-hidden
                    data-chip="current"
                    layoutId={`${group}-current`}
                    transition={GLIDE}
                    className={cn(
                        'absolute inset-0 bg-primary shadow-raised',
                        className,
                    )}
                />
            )}
        </>
    );
}
