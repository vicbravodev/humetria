import InboxEmpty from '@/components/complaints/inbox-empty';
import InboxGroup from '@/components/complaints/inbox-group';
import type { CaseCounts, CaseRow, Paginated } from '@/types';

/** The cases of the inbox by what is due (board V4-Quejas-Bandeja): waiting for their acknowledgement, then in review. */
export default function InboxGroups({
    cases,
    closed,
    counts,
    filtered,
    today,
    manages,
}: {
    cases: Paginated<CaseRow>;
    closed: boolean;
    counts: CaseCounts;
    filtered: boolean;
    today: string;
    manages: boolean;
}) {
    if (cases.total === 0) {
        return (
            <InboxEmpty closed={closed} filtered={filtered} manages={manages} />
        );
    }

    if (closed) {
        return (
            <InboxGroup
                id="closed-cases"
                title="Cerrados"
                count={counts.closed}
                rows={cases.data}
                today={today}
            />
        );
    }

    return (
        <>
            <InboxGroup
                id="awaiting-cases"
                title="Esperan acuse de recibo"
                count={counts.awaiting}
                rows={cases.data.filter((row) => !row.acknowledged)}
                today={today}
            />
            <InboxGroup
                id="review-cases"
                title="En revisión"
                count={counts.open - counts.awaiting}
                rows={cases.data.filter((row) => row.acknowledged)}
                today={today}
            />
        </>
    );
}
