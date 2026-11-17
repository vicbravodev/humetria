import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { weekRange } from '@/lib/attendance/week-fold';

const ARROW =
    'flex size-11 shrink-0 items-center justify-center rounded-lg border border-input bg-card text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/**
 * The bar of the week (hallazgo 21): which one it is between ‹ and › of 44 px, instead of buttons stacked. The next one only
 * once it started; opening another asks for the person's signature again.
 */
export default function WeekNav({
    start,
    end,
    previousUrl,
    nextUrl,
}: {
    start: string;
    end: string;
    previousUrl: string;
    nextUrl: string | null;
}) {
    return (
        <nav
            aria-label="Otras semanas"
            className="flex items-center justify-between gap-2"
        >
            <Link
                href={previousUrl}
                aria-label="Semana anterior"
                className={ARROW}
            >
                <ChevronLeft aria-hidden className="size-5" />
            </Link>
            <span className="text-[17px] font-semibold tabular-nums">
                {weekRange(start, end)}
            </span>
            {nextUrl === null ? (
                <span aria-hidden className="size-11 shrink-0" />
            ) : (
                <Link
                    href={nextUrl}
                    aria-label="Semana siguiente"
                    className={ARROW}
                >
                    <ChevronRight aria-hidden className="size-5" />
                </Link>
            )}
        </nav>
    );
}
