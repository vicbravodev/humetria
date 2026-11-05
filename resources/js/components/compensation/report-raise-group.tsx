import BarMeter from '@/components/humetria/bar-meter';
import type { RaiseGroup } from '@/lib/compensation/report-charts';

/** One group of «Quién recibe el aumento»: its raise and people, and a bar per category on the common scale. */
export default function ReportRaiseGroup({
    group,
    offset,
}: {
    group: RaiseGroup;
    /** The bars drawn before this group, so they keep growing one after another. */
    offset: number;
}) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="flex items-baseline justify-between gap-3">
                <span className="font-semibold">{group.title}</span>
                <span className="text-caption text-muted-foreground">
                    {group.people}
                </span>
            </h3>
            <ul className="flex flex-col gap-3">
                {group.rows.map((row, index) => (
                    <li
                        key={row.key}
                        className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)_4.5rem]"
                    >
                        <span className="flex min-w-0 flex-col sm:ps-1">
                            <span className="truncate">{row.label}</span>
                            <span className="text-caption text-muted-foreground">
                                {row.people}
                            </span>
                        </span>
                        <BarMeter
                            percent={row.percent}
                            tooltip={row.tooltip}
                            index={offset + index}
                            className="col-span-2 row-start-2 sm:col-span-1 sm:row-start-auto"
                        />
                        <span className="col-start-2 row-start-1 text-right font-semibold tabular-nums sm:col-start-auto sm:row-start-auto">
                            {row.valueText}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
