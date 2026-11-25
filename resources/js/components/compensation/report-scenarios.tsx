import { Link } from '@inertiajs/react';
import ReportPart from '@/components/compensation/report-part';
import ReportScenarioTable from '@/components/compensation/report-scenario-table';
import { useScenarioRoutes } from '@/hooks/use-scenario-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { compareIds } from '@/lib/compensation/report-charts';
import {
    ladderRows,
    otherRows,
    pointNote,
} from '@/lib/compensation/report-scenarios';
import type { ScenarioReport } from '@/types';

/**
 * «Frente a otros niveles y otras propuestas»: the same raise two points apart, and the other active proposals of the account,
 * each with what the first year costs, how much the payroll really grows and a person a month; and what a point costs.
 */
export default function ReportScenarios({
    report,
    title,
}: {
    report: ScenarioReport;
    title: string;
}) {
    const tenantSlug = useTenantSlug();
    const { compare } = useScenarioRoutes();
    const ladder = ladderRows(report);
    const others = report.others.length > 0 ? otherRows(report) : [];

    return (
        <ReportPart
            id="frente"
            pageBreak
            title={title}
            description="Costo del primer año, cuánto sube de verdad la nómina y cuánto cuesta por persona. Tu propuesta está resaltada."
        >
            {ladder.length > 0 && (
                <div className="flex break-inside-avoid flex-col gap-3">
                    <h3 className="font-semibold">
                        Si el aumento fuera distinto
                    </h3>
                    <div className="card">
                        <ReportScenarioTable
                            caption="El mismo aumento a otros niveles"
                            rows={ladder}
                        />
                        <p className="border-t px-4 py-3 text-sm text-muted-foreground">
                            {pointNote(report)}
                        </p>
                    </div>
                </div>
            )}
            {others.length > 0 && (
                <div className="flex break-inside-avoid flex-col gap-3">
                    <h3 className="font-semibold">Tus otras propuestas</h3>
                    <div className="card">
                        <ReportScenarioTable
                            caption="Tus propuestas activas"
                            rows={others}
                        />
                    </div>
                    <Link
                        href={compare(tenantSlug, {
                            query: { ids: compareIds(report) },
                        })}
                        className="touch-hit relative self-start text-sm font-medium text-primary underline-offset-4 hover:underline print:hidden"
                    >
                        Ver la comparación completa
                    </Link>
                </div>
            )}
            {ladder.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {pointNote(report)}
                </p>
            )}
        </ReportPart>
    );
}
