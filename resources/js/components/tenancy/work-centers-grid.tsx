import CreateWorkCenterDialog from '@/components/tenancy/create-work-center-dialog';
import WorkCenterCard from '@/components/tenancy/work-center-card';
import type {
    MexicanStateOption,
    WorkCenterRow,
    WorkCentersView,
} from '@/types';

/** The work centers of one page as cards; among the active ones, the last tile adds another. */
export default function WorkCentersGrid({
    view,
    workCenters,
    states,
    full,
}: {
    view: WorkCentersView;
    workCenters: WorkCenterRow[];
    /** The states the forms offer; null when the person may not change work centers. */
    states: MexicanStateOption[] | null;
    /** The plan has no room for another center: the tile that adds one is off. */
    full: boolean;
}) {
    return (
        <section
            aria-label={
                view === 'active'
                    ? 'Centros de trabajo'
                    : 'Centros de trabajo archivados'
            }
            className="grid grid-cols-[repeat(auto-fill,minmax(min(400px,100%),1fr))] gap-5"
        >
            {workCenters.map((workCenter) => (
                <WorkCenterCard
                    key={workCenter.id}
                    workCenter={workCenter}
                    states={states}
                />
            ))}
            {view === 'active' && states !== null && (
                <CreateWorkCenterDialog states={states} tile full={full} />
            )}
        </section>
    );
}
