import { describeDate } from '@/lib/calendar-date';
import type { ReviewDecisionRecord } from '@/types';

/** A decision already made: what, how, when, and the notes and plan that came with it. */
export default function ReviewDecisionCard({
    title,
    record,
}: {
    title: string;
    record: ReviewDecisionRecord;
}) {
    const due =
        record.planDueOn === null ? undefined : describeDate(record.planDueOn);

    return (
        <section className="grid gap-2 rounded-xl border bg-card p-5">
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="font-medium">{record.decisionLabel}</p>
            <p className="text-[15px] text-muted-foreground">
                {record.methodLabel} · {describeDate(record.decidedOn)}
            </p>
            {record.notes !== '' && (
                <p className="text-[15px] leading-[22px]">{record.notes}</p>
            )}
            {record.actionPlan !== null && (
                <p className="text-[15px] leading-[22px]">
                    <strong className="font-semibold">Plan:</strong>{' '}
                    {record.actionPlan}
                    {due !== undefined && ` (compromiso: ${due})`}
                </p>
            )}
        </section>
    );
}
