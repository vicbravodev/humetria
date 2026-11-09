import DisputeForm from '@/components/attendance/dispute-form';
import { Badge } from '@/components/ui/badge';
import type { WeekView } from '@/lib/attendance/week';
import { statusText } from '@/lib/attendance/week-text';

const BADGES: Record<
    string,
    'warning' | 'success' | 'destructive' | 'secondary'
> = {
    pending: 'warning',
    confirmed: 'success',
    disputed: 'destructive',
    open: 'secondary',
};

/**
 * Where the week stands and, when the person says what is not right, the form for it. The buttons that answer are at the foot
 * of the page (WeekActions); each asks for the person's fingerprint, face or PIN again.
 */
export default function WeekAnswer({
    week,
    disputing,
    working,
    onCloseDispute,
    onDispute,
}: {
    week: WeekView;
    disputing: boolean;
    working: boolean;
    onCloseDispute: () => void;
    onDispute: (url: string, reason: string) => Promise<boolean>;
}) {
    const { label, detail } = statusText(week);
    const { disputeUrl } = week;

    return (
        <section
            aria-labelledby="week-answer-title"
            className="flex flex-col gap-3 card p-5"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2
                    id="week-answer-title"
                    className="text-xl leading-7 font-semibold"
                >
                    Su respuesta
                </h2>
                <Badge variant={BADGES[week.status] ?? 'secondary'}>
                    {label}
                </Badge>
            </div>
            <p role="status" className="text-base">
                {detail}
            </p>
            {week.disputeReason !== null && (
                <blockquote className="border-l-2 pl-3 text-base text-muted-foreground">
                    {week.disputeReason}
                </blockquote>
            )}
            {disputing && disputeUrl !== null && (
                <DisputeForm
                    working={working}
                    onCancel={onCloseDispute}
                    onSend={(reason) =>
                        void onDispute(disputeUrl, reason).then(
                            (done) => done && onCloseDispute(),
                        )
                    }
                />
            )}
        </section>
    );
}
