import { SearchX } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import WorkCentersArchivedTable from '@/components/tenancy/work-centers-archived-table';
import WorkCentersEmpty from '@/components/tenancy/work-centers-empty';
import WorkCentersGrid from '@/components/tenancy/work-centers-grid';
import type {
    MexicanStateOption,
    Paginated,
    WorkCenterRow,
    WorkCentersView,
} from '@/types';

/**
 * One page of work centers: the active ones as cards, the archived ones as a table (Card.md, Table.md); when there
 * are none, why.
 */
export default function WorkCentersList({
    view,
    search,
    workCenters,
    manage,
}: {
    view: WorkCentersView;
    search: string;
    workCenters: Paginated<WorkCenterRow>;
    /** What changing the work centers needs; null when the person may not. */
    manage: { states: MexicanStateOption[]; full: boolean } | null;
}) {
    if (workCenters.total === 0) {
        return search === '' ? (
            <WorkCentersEmpty view={view} />
        ) : (
            <EmptyState
                icon={SearchX}
                title={`Ningún centro coincide con «${search}»`}
            >
                Revisa cómo se escribe o busca por el municipio.
            </EmptyState>
        );
    }

    return view === 'active' ? (
        <WorkCentersGrid
            workCenters={workCenters.data}
            states={manage?.states ?? null}
            full={manage?.full ?? false}
        />
    ) : (
        <WorkCentersArchivedTable
            workCenters={workCenters.data}
            states={manage?.states ?? null}
        />
    );
}
