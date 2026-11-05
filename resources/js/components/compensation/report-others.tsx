import { Link } from '@inertiajs/react';
import ReportPart from '@/components/compensation/report-part';
import BarMeter from '@/components/humetria/bar-meter';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { compareIds, otherBars } from '@/lib/compensation/report-charts';
import { cn } from '@/lib/utils';
import { compare } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioReport } from '@/types';

/** «Frente a las otras propuestas»: the first year of this one against the other active scenarios of the account (D1). */
export default function ReportOthers({
    report,
    title,
}: {
    report: ScenarioReport;
    title: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ReportPart
            id="frente"
            title={title}
            description="Costo del primer año de cada escenario. Esta propuesta está resaltada."
        >
            <div className="flex break-inside-avoid flex-col gap-4 card p-5 sm:p-6">
                <ul className="flex flex-col gap-3">
                    {otherBars(report).map((bar, index) => (
                        <li
                            key={bar.key}
                            className={cn(
                                'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)_7rem]',
                                bar.current
                                    ? 'font-semibold'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <span className="truncate">{bar.name}</span>
                            <BarMeter
                                percent={bar.percent}
                                tooltip={`${bar.name}: ${bar.text}`}
                                muted={!bar.current}
                                index={index}
                                className="col-span-2 row-start-2 sm:col-span-1 sm:row-start-auto"
                            />
                            <span className="col-start-2 row-start-1 text-right tabular-nums sm:col-start-auto sm:row-start-auto">
                                {bar.text}
                            </span>
                        </li>
                    ))}
                </ul>
                <Link
                    href={compare(tenantSlug, {
                        query: { ids: compareIds(report) },
                    })}
                    className="touch-hit relative self-start text-sm font-medium text-primary underline-offset-4 hover:underline print:hidden"
                >
                    Ver la comparación completa
                </Link>
            </div>
        </ReportPart>
    );
}
