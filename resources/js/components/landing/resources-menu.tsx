import { ChevronDown } from 'lucide-react';
import { AnimatePresence, m } from 'motion/react';
import { useCallback, useRef, useState } from 'react';
import { EASE_OUT } from '@/components/landing/landing-motion';
import { useDismiss } from '@/hooks/use-dismiss';
import ResourceItem from '@/components/landing/resource-item';
import type { ResourceLink } from '@/components/landing/resource-link';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    test: string;
    items: readonly ResourceLink[];
    /** Where a link goes: its page, or its section of the landing. */
    hrefOf: (item: ResourceLink) => string;
    /** The item being read or visited, to mark it and the trigger. */
    currentId: string | null;
    triggerClassName: string;
};

/** The group «Recursos» of the header: a disclosure with each link and what it is for. Closes with Escape or a click outside. */
export default function ResourcesMenu({
    label,
    test,
    items,
    hrefOf,
    currentId,
    triggerClassName,
}: Props) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const close = useCallback(() => setOpen(false), []);
    useDismiss(open, root, close);

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                aria-expanded={open}
                aria-controls="resources-menu"
                onClick={() => setOpen(!open)}
                data-test={test}
                className={cn('gap-1.5', triggerClassName)}
            >
                {label}
                <ChevronDown
                    aria-hidden
                    className={cn(
                        'size-3.5 transition-transform duration-200 ease-out motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
            </button>
            <AnimatePresence>
                {open && (
                    <m.nav
                        id="resources-menu"
                        aria-label={label}
                        initial={{ opacity: 0, y: -6, scale: 0.98 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{
                            opacity: 0,
                            y: -4,
                            scale: 0.98,
                            transition: { duration: 0.15 },
                        }}
                        transition={{ duration: 0.22, ease: EASE_OUT }}
                        className="absolute top-full right-0 z-20 mt-3 grid w-[360px] origin-top-right gap-1 rounded-3xl border bg-card p-3 text-left shadow-raised"
                    >
                        {items.map((item) => (
                            <ResourceItem
                                key={item.id}
                                item={item}
                                href={hrefOf(item)}
                                current={item.id === currentId}
                                onPick={close}
                            />
                        ))}
                    </m.nav>
                )}
            </AnimatePresence>
        </div>
    );
}
