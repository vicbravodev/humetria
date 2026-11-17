import CreateWorkCenterDialog from '@/components/tenancy/create-work-center-dialog';
import WorkCenterCard from '@/components/tenancy/work-center-card';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

/**
 * The active work centers of one page as cards (Card.md: few, each with its own standing and next step); the last
 * tile adds another. The archived ones go in a table.
 */
export default function WorkCentersGrid({
    workCenters,
    states,
    full,
}: {
    workCenters: WorkCenterRow[];
    /** The states the forms offer; null when the person may not change work centers. */
    states: MexicanStateOption[] | null;
    /** The plan has no room for another center: the tile that adds one is not shown (the notice above says what to do). */
    full: boolean;
}) {
    return (
        <section
            data-tour="work-centers-list"
            aria-label="Centros de trabajo"
            className="grid grid-cols-[repeat(auto-fill,minmax(min(400px,100%),1fr))] gap-5"
        >
            {workCenters.map((workCenter) => (
                <WorkCenterCard
                    key={workCenter.id}
                    workCenter={workCenter}
                    states={states}
                />
            ))}
            {states !== null && !full && (
                <CreateWorkCenterDialog states={states} tile full={full} />
            )}
        </section>
    );
}
