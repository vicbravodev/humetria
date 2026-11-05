import CaseConflict from '@/components/complaints/case-conflict';
import CaseDeadlinesCard from '@/components/complaints/case-deadlines';
import CaseLog from '@/components/complaints/case-log';
import CaseNotes from '@/components/complaints/case-notes';
import type { Option } from '@/lib/option-search';
import type {
    CaseDeadlines,
    CaseDetail,
    CaseEntryRow,
    Paginated,
} from '@/types';

/** The side of a case (board V4-Quejas-Caso): its deadlines, the internal notes, what to do if it is not yours, and its log. */
export default function CaseSide({
    detail,
    deadlines,
    today,
    notes,
    writable,
    candidates,
    log,
    access,
}: {
    detail: CaseDetail;
    deadlines: CaseDeadlines;
    today: string;
    notes: Paginated<CaseEntryRow>;
    writable: boolean;
    candidates: Option[];
    log: Paginated<CaseEntryRow>;
    access: string[];
}) {
    return (
        <aside
            aria-label="Plazos, notas y bitácora"
            className="flex min-w-0 flex-col gap-6"
        >
            <CaseDeadlinesCard
                deadlines={deadlines}
                received={detail.receivedOn}
                today={today}
            />
            <CaseNotes caseId={detail.id} notes={notes} writable={writable} />
            <CaseConflict
                caseId={detail.id}
                candidates={candidates}
                onHold={detail.onHold}
            />
            <CaseLog log={log} access={access} received={detail.receivedOn} />
        </aside>
    );
}
