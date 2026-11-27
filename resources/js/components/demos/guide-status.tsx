import HumiAvatar from '@/components/humetria/humi-avatar';
import { guideCount } from '@/lib/demos/guide';
import type { DemoGuide } from '@/types/demo-guide';

/**
 * The guide folded aside while a dialog or a sheet is open (board Rec-01): the step and what is going on, at the bottom
 * left so it never covers the form. Only to read: the dialog holds the page meanwhile; on a phone the sheet takes it all.
 */
export default function GuideStatus({ guide }: { guide: DemoGuide }) {
    const current = guide.current;

    return (
        <div className="fixed bottom-4 left-4 z-60 flex h-12 max-w-[calc(100vw-2rem)] items-center gap-2.5 rounded-full border bg-card pr-4 pl-2 text-sm shadow-float max-md:hidden print:hidden">
            <HumiAvatar size={32} />
            <span className="truncate font-semibold">
                {current?.title ?? `Guía · ${guideCount(guide)}`}
            </span>
            {current !== null && current.live !== null && (
                <span className="truncate text-muted-foreground tabular-nums">
                    · {current.live}
                </span>
            )}
        </div>
    );
}
