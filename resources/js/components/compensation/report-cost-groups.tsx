import BarMeter from '@/components/humetria/bar-meter';
import { costGroups, unchangedNote } from '@/lib/compensation/report-costs';
import type { ScenarioReport } from '@/types';

/** «Qué sube y por qué»: each source of the cost with its share of the first year, and what does not move with the raise. */
export default function ReportCostGroups({
    report,
}: {
    report: ScenarioReport;
}) {
    const unchanged = unchangedNote(report);

    return (
        <div className="flex break-inside-avoid flex-col gap-4 card p-5 sm:p-6">
            <ul className="flex flex-col gap-4">
                {costGroups(report).map((group, index) => (
                    <li
                        key={group.key}
                        className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[minmax(0,17rem)_minmax(0,1fr)_9rem]"
                    >
                        <div className="flex flex-col">
                            <span className="font-medium">{group.label}</span>
                            <span className="text-sm text-muted-foreground">
                                {group.note}
                            </span>
                        </div>
                        <BarMeter
                            percent={group.percent}
                            tooltip={`${group.label}, ${group.shareText} del costo del primer año`}
                            index={index}
                            className="col-span-2 row-start-2 sm:col-span-1 sm:row-start-auto"
                        />
                        <span className="col-start-2 row-start-1 flex flex-col text-right tabular-nums sm:col-start-auto sm:row-start-auto">
                            <span className="font-semibold">
                                {group.valueText}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {group.shareText}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
            {unchanged !== null && (
                <p className="border-t pt-4 text-sm text-muted-foreground">
                    {unchanged}
                </p>
            )}
        </div>
    );
}
