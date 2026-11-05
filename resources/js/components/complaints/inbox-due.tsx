import DueBar from '@/components/complaints/due-bar';
import { inboxDue } from '@/lib/complaints/inbox';
import { cn } from '@/lib/utils';
import type { CaseRow } from '@/types';

/** The deadline that counts now for a case of the inbox, in words and as a bar; a closed one says how it was closed. */
export default function InboxDue({
    row,
    today,
}: {
    row: CaseRow;
    today: string;
}) {
    if (row.status === 'closed') {
        return (
            <span className="text-sm text-muted-foreground">
                {row.resolution ?? row.statusLabel}
            </span>
        );
    }

    const due = inboxDue(row, today);

    return (
        <div className="flex flex-col gap-1.5">
            <span
                className={cn(
                    'text-sm',
                    due.urgent && 'font-medium text-risk-alto-fg',
                )}
            >
                {due.text}
            </span>
            <DueBar share={due.share} urgent={due.urgent} />
        </div>
    );
}
