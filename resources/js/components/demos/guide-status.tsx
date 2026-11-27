import HumiAvatar from '@/components/humetria/humi-avatar';
import { guideCount } from '@/lib/demos/guide';
import type { DemoGuide } from '@/types/demo-guide';

/**
 * The guide folded aside while a dialog or a sheet is open (board Rec-01): the step and what is going on, at the bottom
 * left so it never covers the form; on a phone, only the step, small at the top, where the sheets leave room. Only to read:
 * the dialog holds the page meanwhile.
 */
export default function GuideStatus({ guide }: { guide: DemoGuide }) {
    const current = guide.current;

    return (
        <div
            data-slot="guide-status"
            className="pointer-events-none fixed bottom-4 left-4 z-60 flex h-12 max-w-[calc(100vw-2rem)] items-center gap-2.5 rounded-full border bg-card pr-4 pl-2 text-sm shadow-float max-md:top-2 max-md:bottom-auto max-md:left-1/2 max-md:h-10 max-md:max-w-[70vw] max-md:-translate-x-1/2 print:hidden"
        >
            <HumiAvatar size={32} />
            <span className="truncate font-semibold">
                {current?.title ?? `Guía · ${guideCount(guide)}`}
            </span>
            {current !== null && current.live !== null && (
                <span className="truncate text-muted-foreground tabular-nums max-md:hidden">
                    · {current.live}
                </span>
            )}
        </div>
    );
}
