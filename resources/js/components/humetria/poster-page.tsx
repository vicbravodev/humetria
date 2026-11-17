import { Lightbulb } from 'lucide-react';
import type { ReactNode } from 'react';
import { useFitScale } from '@/hooks/use-fit-scale';

/** A letter sheet at 96 px per inch: 8.5 × 11 in. */
const SHEET_WIDTH = 816;
const SHEET_HEIGHT = 1056;

/**
 * The page of a poster to print (sección 10 del pulido): from `lg`, the controls and a few tips at the left, fixed while the page
 * scrolls, and the sheet at the right on a muted stage; stacked on a phone. The sheet is the paper: letter-sized with the margins
 * of the `certificate` page, always light whatever the theme, and scaled to be seen whole. On paper only the sheet is left.
 */
export default function PosterPage({
    controls,
    tips,
    children,
}: {
    /** The header, what to print and the actions. */
    controls: ReactNode;
    tips: string[];
    /** What is printed on the sheet. */
    children: ReactNode;
}) {
    const { ref, scale } = useFitScale<HTMLDivElement>(
        SHEET_WIDTH,
        SHEET_HEIGHT,
    );

    return (
        <div className="grid w-full items-start gap-6 px-4 py-6 md:px-10 md:py-8 lg:grid-cols-[360px_minmax(0,1fr)] lg:gap-10 print:block print:p-0">
            <div
                data-slot="poster-controls"
                className="flex min-w-0 flex-col gap-6 lg:sticky lg:top-24 print:hidden"
            >
                {controls}
                <ul className="flex flex-col gap-3 border-t pt-5 text-sm text-muted-foreground">
                    {tips.map((tip) => (
                        <li key={tip} className="flex gap-3">
                            <Lightbulb
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            {tip}
                        </li>
                    ))}
                </ul>
            </div>
            <div
                ref={ref}
                className="flex aspect-[8.5/11] items-start justify-center overflow-hidden rounded-xl bg-muted p-3 md:p-8 lg:aspect-auto lg:h-[calc(100svh-9rem)] print:block print:aspect-auto print:h-auto print:overflow-visible print:rounded-none print:bg-transparent print:p-0"
            >
                <article
                    data-slot="poster-sheet"
                    style={{ zoom: scale }}
                    className="light flex h-[1056px] w-[816px] shrink-0 flex-col justify-between gap-7 rounded-sm bg-card p-19 text-card-foreground shadow-float [page:certificate] print:h-auto print:w-auto print:[zoom:1]! print:rounded-none print:p-0 print:shadow-none"
                >
                    {children}
                </article>
            </div>
        </div>
    );
}
