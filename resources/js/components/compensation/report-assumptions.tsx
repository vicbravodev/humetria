import { describeDate } from '@/lib/calendar-date';
import type { ScenarioReport } from '@/types';

/** Every assumption of the long term with its value, where it comes from and when it was set. */
export default function ReportAssumptions({
    assumptions,
}: {
    assumptions: ScenarioReport['assumptions'];
}) {
    return (
        <dl className="grid gap-4">
            {assumptions.map((assumption) => (
                <div
                    key={assumption.label}
                    className="grid gap-1 border-t pt-4 first:border-t-0 first:pt-0"
                >
                    <dt className="flex flex-wrap items-baseline justify-between gap-x-4 font-medium">
                        {assumption.label}
                        <span className="tabular-nums">
                            {assumption.value} % al año
                        </span>
                    </dt>
                    <dd className="text-sm text-muted-foreground">
                        {assumption.source} · Fijado el{' '}
                        {describeDate(assumption.set_on) ?? assumption.set_on}.
                    </dd>
                </div>
            ))}
        </dl>
    );
}
