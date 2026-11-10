import ProgressBar from '@/components/humetria/progress-bar';
import { confirmedHint } from '@/lib/attendance/report-findings';

/** «Días confirmados» (board A7): the share of the period's days whose week the person confirmed, with its bar. */
export default function ReportConfirmed({
    percent,
    confirmed,
    days,
}: {
    percent: number;
    confirmed: number;
    days: number;
}) {
    return (
        <div className="flex flex-col gap-1 card px-5 py-[18px]">
            <span className="text-sm leading-5 text-muted-foreground">
                Días confirmados
            </span>
            <div className="flex flex-wrap items-baseline gap-x-2">
                <span className="text-3xl leading-[38px] font-semibold tracking-[-0.02em] whitespace-nowrap tabular-nums">
                    {percent} %
                </span>
                <span className="text-caption text-muted-foreground">
                    {confirmedHint(confirmed, days)}
                </span>
            </div>
            <ProgressBar
                value={confirmed}
                max={days}
                label="Días confirmados"
            />
        </div>
    );
}
