import { describeDate } from '@/lib/calendar-date';
import type { TeleworkerSummary } from '@/types';

/** A period that ended: how, when and the note, kept as history. */
export default function RosterEnded({
    teleworker,
}: {
    teleworker: TeleworkerSummary;
}) {
    return (
        <section
            className="grid gap-1 rounded-xl border bg-card p-5"
            aria-labelledby="roster-title"
        >
            <h2 id="roster-title" className="text-base font-semibold">
                Teletrabajo terminado
            </h2>
            <p className="text-[15px] text-muted-foreground">
                {teleworker.endReason} ·{' '}
                {describeDate(teleworker.endedOn ?? '') ?? ''}
            </p>
            {teleworker.endNote !== null && (
                <p className="text-[15px]">{teleworker.endNote}</p>
            )}
        </section>
    );
}
