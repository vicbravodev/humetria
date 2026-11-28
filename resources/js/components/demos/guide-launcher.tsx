import HumiAvatar from '@/components/humetria/humi-avatar';
import { guideCount } from '@/lib/demos/guide';
import { cn } from '@/lib/utils';
import type { DemoGuide } from '@/types/demo-guide';

/**
 * The guide's pill, «Guía · 4 de 15», in the place of the first steps (one guide at a time) beside Humi: it opens and folds
 * the dock. On a phone the open guide covers it, and folds from its own button.
 */
export default function GuideLauncher({
    guide,
    open,
    onToggle,
}: {
    guide: DemoGuide;
    open: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            aria-expanded={open}
            aria-controls="demo-guide"
            onClick={onToggle}
            className={cn(
                'fixed right-[4.75rem] bottom-(--tab-bar-clearance) z-40 flex h-12 items-center gap-2.5 rounded-full border bg-card pr-4 pl-2 text-sm font-semibold shadow-float transition-transform duration-150 ease-out group-has-[aside[aria-label=Humi]]/sidebar-wrapper:right-[436px] hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none md:bottom-4 print:hidden',
                open && 'max-md:hidden',
            )}
        >
            <HumiAvatar size={32} />
            <span>
                Guía · <span className="tabular-nums">{guideCount(guide)}</span>
            </span>
        </button>
    );
}
