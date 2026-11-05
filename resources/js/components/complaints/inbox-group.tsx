import InboxRow from '@/components/complaints/inbox-row';
import type { CaseRow } from '@/types';

/** A group of the inbox by what is due (board V4-Quejas-Bandeja): its title with how many, and its cases in a card. */
export default function InboxGroup({
    id,
    title,
    count,
    rows,
    today,
}: {
    id: string;
    title: string;
    count: number;
    rows: CaseRow[];
    today: string;
}) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby={id} className="flex flex-col gap-3">
            <h2 id={id} className="text-lg font-semibold">
                {title}{' '}
                <span className="font-normal text-muted-foreground">
                    · {count}
                </span>
            </h2>
            <ul className="@container flex flex-col divide-y overflow-hidden card">
                {rows.map((row) => (
                    <InboxRow key={row.id} row={row} today={today} />
                ))}
            </ul>
        </section>
    );
}
