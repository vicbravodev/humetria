import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useScenarioRoutes } from '@/hooks/use-scenario-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportAccuracy } from '@/lib/compensation/report-notes';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';
import type { ScenarioReport } from '@/types';

/**
 * The one notice of accuracy of the report (C6), in place of a warning for each thing that is missing: how many data keep the
 * number from being exact and which, with «Completar datos» for whoever manages the data of the contract.
 */
export default function ReportAccuracy({
    report,
    canComplete,
}: {
    report: ScenarioReport;
    canComplete: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { edit } = useScenarioRoutes();
    const average = report.scenario.source === 'average';
    const accuracy = reportAccuracy(report);

    if (accuracy === null) {
        return null;
    }

    return (
        <Notice
            action={
                canComplete ? (
                    <Button
                        variant="outline"
                        asChild
                        className="max-sm:w-full print:hidden"
                    >
                        {/* An average payroll has no roster or tabulator to complete: what it lacks is typed in the scenario. */}
                        <Link
                            href={
                                average
                                    ? edit({
                                          tenant: tenantSlug,
                                          scenario: report.scenario.id,
                                      })
                                    : tabulator(tenantSlug)
                            }
                        >
                            {average
                                ? 'Completar en el escenario'
                                : 'Completar datos'}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <strong>{accuracy.title}</strong> {accuracy.detail}
        </Notice>
    );
}
