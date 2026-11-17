import ProgramCard from '@/components/results/program-card';
import TraumaSummary from '@/components/results/trauma-summary';
import { cn } from '@/lib/utils';
import type { ResultsProgramCard, ScreeningCounts } from '@/types';

/**
 * The row under the overview: the Guía I (for who channels people) and the Programa (when the norm asks for one), each
 * optional. The columns follow how many come: one alone takes the whole row, in a single line, instead of leaving
 * half of it empty (Card.md).
 */
export default function ResultsSideCards({
    route,
    trauma,
    program,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    trauma: ScreeningCounts | null;
    /** Null when there is no card, or the person may not read the Programa. */
    program: ResultsProgramCard | null;
}) {
    if (trauma === null && program === null) {
        return null;
    }

    const alone = trauma === null || program === null;

    return (
        <div
            className={cn('grid items-start gap-5', !alone && 'lg:grid-cols-2')}
        >
            {trauma !== null && (
                <TraumaSummary url={route} counts={trauma} wide={alone} />
            )}
            {program !== null && (
                <ProgramCard route={route} program={program} wide={alone} />
            )}
        </div>
    );
}
