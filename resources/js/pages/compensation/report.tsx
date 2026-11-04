import { Head, Link } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import ReportAssumptions from '@/components/compensation/report-assumptions';
import ReportConcepts from '@/components/compensation/report-concepts';
import ReportCoverage from '@/components/compensation/report-coverage';
import ReportCuts from '@/components/compensation/report-cuts';
import ReportHeadline from '@/components/compensation/report-headline';
import ReportLiability from '@/components/compensation/report-liability';
import ReportPersonValue from '@/components/compensation/report-person-value';
import ReportTimeline from '@/components/compensation/report-timeline';
import ReportWarnings from '@/components/compensation/report-warnings';
import PageHeader from '@/components/humetria/page-header';
import PrintButton from '@/components/humetria/print-button';
import { Button } from '@/components/ui/button';
import { useIsUnion } from '@/hooks/use-is-union';
import { useLightPrint } from '@/hooks/use-light-print';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { raiseSummary } from '@/lib/compensation/scenarios';
import { annex, edit } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioReport } from '@/types';

export default function Report(report: ScenarioReport) {
    const tenantSlug = useTenantSlug();
    const isUnion = useIsUnion();
    const { scenario } = report;
    useLightPrint();

    return (
        <>
            <Head title={`Reporte · ${scenario.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16 print:p-0">
                <PageHeader
                    caption="Reporte de impacto"
                    title={scenario.name}
                    description={`Desde el ${scenario.effective_on} · ${raiseSummary(scenario.unionized_raise, scenario.trusted_raise)}.`}
                >
                    <Button variant="outline" asChild className="print:hidden">
                        <Link
                            href={edit({
                                tenant: tenantSlug,
                                scenario: scenario.id,
                            })}
                        >
                            <Pencil />
                            Editar escenario
                        </Link>
                    </Button>
                    {report.can_annex && (
                        <Button
                            variant="outline"
                            asChild
                            className="print:hidden"
                        >
                            <a
                                href={annex.url({
                                    tenant: tenantSlug,
                                    scenario: scenario.id,
                                })}
                                download
                            >
                                <Download />
                                Anexo por persona
                            </a>
                        </Button>
                    )}
                    <span className="print:hidden">
                        <PrintButton />
                    </span>
                </PageHeader>
                <ReportCoverage report={report} />
                <ReportWarnings report={report} />
                <ReportHeadline report={report} />
                <ReportConcepts concepts={report.concepts} />
                {isUnion && <ReportPersonValue value={report.person_value} />}
                <ReportCuts cuts={report.cuts} />
                <ReportTimeline report={report} />
                <ReportLiability report={report} />
                <ReportAssumptions assumptions={report.assumptions} />
            </div>
        </>
    );
}

Report.layout = {
    breadcrumbs: [
        { title: 'Herramientas' },
        { title: 'Compensación' },
        { title: 'Escenarios' },
        { title: 'Reporte' },
    ],
};
