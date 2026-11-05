import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Download, Pencil } from 'lucide-react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ReportBody from '@/components/compensation/report-body';
import ReportShares from '@/components/compensation/report-shares';
import ShareDialog from '@/components/compensation/share-dialog';
import { Button } from '@/components/ui/button';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportSubtitle } from '@/lib/compensation/report-notes';
import { index } from '@/routes/tenant/compensation';
import { annex, edit } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioReport } from '@/types';

/** The report of impact of a scenario with its analysis (board V4-Comp-Reporte), of a company or of a union with its own data. */
export default function Report(report: ScenarioReport) {
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();
    const company = usePage().props.tenant?.trade_name ?? null;
    const { scenario } = report;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: scenario.name },
        ],
    });

    return (
        <>
            <Head title={`Reporte · ${scenario.name}`} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16 print:p-0">
                <CompensationHeader
                    eyebrow={
                        union
                            ? 'Reporte de impacto · Propuesta del sindicato'
                            : 'Reporte de impacto · Compensación'
                    }
                    title={scenario.name}
                    lead={reportSubtitle(report, company)}
                >
                    <Button variant="outline" asChild className="print:hidden">
                        <Link
                            href={edit({
                                tenant: tenantSlug,
                                scenario: scenario.id,
                            })}
                        >
                            <Pencil aria-hidden />
                            Editar
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
                                <Download aria-hidden />
                                Anexo por persona
                            </a>
                        </Button>
                    )}
                    {report.shares !== null && (
                        <span className="print:hidden">
                            <ShareDialog scenarioId={scenario.id} />
                        </span>
                    )}
                </CompensationHeader>
                <ReportBody
                    report={report}
                    union={union}
                    shared={false}
                    stickyClassName="top-16"
                />
                {report.shares !== null && (
                    <div className="print:hidden">
                        <ReportShares
                            scenarioId={scenario.id}
                            shares={report.shares}
                        />
                    </div>
                )}
            </div>
        </>
    );
}
