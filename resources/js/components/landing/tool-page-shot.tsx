import { cn } from '@/lib/utils';
import type { ToolPageFeature } from '@/types';

/**
 * A real screenshot of the demo company: the panel in the theme of the page (one file per theme), or the phone screen
 * of the person in a phone frame. Its size is declared, so the page does not jump while it loads.
 */
export default function ToolPageShot({
    feature,
}: {
    feature: ToolPageFeature;
}) {
    if (feature.shot.endsWith('-mobile')) {
        return (
            <div className="grid place-items-center rounded-[28px] bg-band py-8">
                <img
                    src={`/images/tools/${feature.shot}.webp`}
                    alt={feature.alt}
                    width={585}
                    height={1266}
                    loading="lazy"
                    className="w-56 rounded-[30px] border-[6px] border-foreground bg-foreground shadow-raised sm:w-64"
                />
            </div>
        );
    }

    const frame = 'block h-auto w-full';

    return (
        <div className="overflow-hidden rounded-2xl border bg-card shadow-raised">
            <img
                src={`/images/tools/${feature.shot}-light.webp`}
                alt={feature.alt}
                width={1520}
                height={1100}
                loading="lazy"
                className={cn(frame, 'dark:hidden')}
            />
            <img
                src={`/images/tools/${feature.shot}-dark.webp`}
                alt={feature.alt}
                width={1520}
                height={1100}
                loading="lazy"
                className={cn(frame, 'hidden dark:block')}
            />
        </div>
    );
}
