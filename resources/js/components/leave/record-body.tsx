import RecordRequests from '@/components/leave/record-requests';
import RecordStatements from '@/components/leave/record-statements';
import RecordTabs from '@/components/leave/record-tabs';
import type { RecordTab } from '@/components/leave/record-tabs';
import LedgerTable from '@/components/leave/ledger-table';
import PeriodTimeline from '@/components/leave/period-timeline';
import { useHashTab } from '@/hooks/use-hash-tab';
import { tabCounts } from '@/lib/leave/record';
import type {
    LeaveLedgerEntry,
    LeaveRecordPeriod,
    LeaveRecordRequest,
    LeaveRecordStatement,
    Paginated,
} from '@/types';

const KEYS: readonly RecordTab[] = [
    'movements',
    'requests',
    'statements',
    'periods',
];

/** What sits under the period of the record: a tab each for the movements, the requests, the statements and the periods. */
export default function RecordBody({
    canManage,
    periods,
    entries,
    requests,
    statements,
    onAdjust,
}: {
    canManage: boolean;
    periods: LeaveRecordPeriod[];
    entries: Paginated<LeaveLedgerEntry>;
    requests: Paginated<LeaveRecordRequest>;
    statements: LeaveRecordStatement[];
    onAdjust: (period: LeaveRecordPeriod) => void;
}) {
    const [tab, setTab] = useHashTab<RecordTab>('movements', KEYS, '');
    const counts = tabCounts({
        movements: entries.total,
        requests: requests.total,
        statements,
        periods: periods.length,
    });

    return (
        <section className="flex flex-col gap-4">
            <RecordTabs selected={tab} counts={counts} onSelect={setTab} />
            <div
                role="tabpanel"
                id={`record-panel-${tab}`}
                aria-labelledby={`record-tab-${tab}`}
            >
                {tab === 'movements' && <LedgerTable entries={entries} />}
                {tab === 'requests' && <RecordRequests requests={requests} />}
                {tab === 'statements' && (
                    <RecordStatements statements={statements} />
                )}
                {tab === 'periods' && (
                    <PeriodTimeline
                        periods={periods}
                        onAdjust={canManage ? onAdjust : undefined}
                    />
                )}
            </div>
        </section>
    );
}
