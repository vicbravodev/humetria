import { Link } from '@inertiajs/react';
import ReportPart from '@/components/compensation/report-part';
import ColumnChart from '@/components/humetria/column-chart';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { yearColumns } from '@/lib/compensation/report-charts';
import { edit } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioReport } from '@/types';

/** «Los próximos años»: what the proposal adds each year of its horizon with the engine's projection, and the assumptions behind it. */
export default function ReportYears({
    report,
    title,
    canChange,
}: {
    report: ScenarioReport;
    title: string;
    canChange: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { columns, totalText } = yearColumns(report);

    return (
        <ReportPart
            id="anios"
            title={title}
            description="Lo que esta propuesta suma cada año, con tus supuestos de plantilla y revisiones futuras."
        >
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
                <div className="break-inside-avoid card p-5 sm:p-6">
                    <ColumnChart
                        label={`Costo adicional por año, ${columns[0]?.label ?? ''} a ${columns.at(-1)?.label ?? ''}`}
                        columns={columns}
                    />
                </div>
                <aside
                    aria-labelledby="report-years-total"
                    className="flex break-inside-avoid flex-col gap-3 self-start card p-5"
                >
                    <p className="text-caption font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                        En {columns.length} años
                    </p>
                    <p
                        id="report-years-total"
                        className="text-[26px] leading-8 font-semibold tabular-nums"
                    >
                        {totalText}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Es una estimación. Cambia si cambian los supuestos.
                    </p>
                    <dl className="grid gap-2 border-t pt-3 text-sm">
                        {report.assumptions.map((assumption) => (
                            <div
                                key={assumption.label}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {assumption.label}
                                </dt>
                                <dd className="shrink-0 font-medium tabular-nums">
                                    {assumption.value} % al año
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {canChange && (
                        <Link
                            href={edit({
                                tenant: tenantSlug,
                                scenario: report.scenario.id,
                            })}
                            className="touch-hit relative self-start text-sm font-medium text-primary underline-offset-4 hover:underline print:hidden"
                        >
                            Cambiar supuestos
                        </Link>
                    )}
                </aside>
            </div>
        </ReportPart>
    );
}
