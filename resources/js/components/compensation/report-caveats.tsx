import { Link } from '@inertiajs/react';
import ReportAssumptions from '@/components/compensation/report-assumptions';
import ReportPart from '@/components/compensation/report-part';
import { Button } from '@/components/ui/button';
import { useScenarioRoutes } from '@/hooks/use-scenario-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportCaveats } from '@/lib/compensation/report-caveats';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';
import type { ScenarioReport } from '@/types';

/**
 * «Supuestos y salvedades», all in one place: what is missing for the number to be exact, the premium of class I, the values not yet
 * published, what is never calculated, and every assumption of the long term with where it comes from.
 */
export default function ReportCaveats({
    report,
    title,
    canFix,
}: {
    report: ScenarioReport;
    title: string;
    canFix: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { edit } = useScenarioRoutes();
    const average = report.scenario.source === 'average';
    // An average payroll has no roster or tabulator to complete: what it lacks is typed in the scenario.
    const complete = average
        ? edit({ tenant: tenantSlug, scenario: report.scenario.id })
        : tabulator(tenantSlug);

    return (
        <ReportPart
            id="supuestos"
            title={title}
            description="Ninguna cifra va sin sus supuestos. Esto es lo que debes saber antes de confiar en el número."
        >
            <ul className="flex flex-col divide-y card">
                {reportCaveats(report).map((caveat) => (
                    <li
                        key={caveat.key}
                        className="flex break-inside-avoid flex-wrap items-center justify-between gap-3 px-5 py-4"
                    >
                        <p className="max-w-[820px] text-sm">
                            <strong className="font-semibold">
                                {caveat.title}
                            </strong>{' '}
                            <span className="text-muted-foreground">
                                {caveat.detail}
                            </span>
                        </p>
                        {canFix && caveat.action !== null && (
                            <Button
                                variant="outline"
                                asChild
                                className="max-sm:w-full print:hidden"
                            >
                                <Link
                                    href={
                                        caveat.action === 'premium'
                                            ? benefits(tenantSlug)
                                            : complete
                                    }
                                >
                                    {caveat.action === 'premium'
                                        ? 'Capturar mi prima'
                                        : average
                                          ? 'Completar en el escenario'
                                          : 'Completar datos'}
                                </Link>
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
            <div className="break-inside-avoid card p-5 sm:p-6">
                <ReportAssumptions assumptions={report.assumptions} />
            </div>
        </ReportPart>
    );
}
