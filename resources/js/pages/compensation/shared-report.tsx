import { Head } from '@inertiajs/react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ReportPrintHeader from '@/components/compensation/report-print-header';
import ReportBody from '@/components/compensation/report-body';
import SharedUnavailable from '@/components/compensation/shared-unavailable';
import CompanyMark from '@/components/company-mark';
import PrintButton from '@/components/humetria/print-button';
import ReportLinkBar from '@/components/humetria/report-link-bar';
import ReportLinkNotice from '@/components/humetria/report-link-notice';
import { reportSubtitle, sharedNotice } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/**
 * The report of a scenario opened from a link that expires (board V4-Comp-Reporte, «compartida por enlace»): the same report,
 * read-only, with the small groups folded, under the common bar of the reports by a link (board ReporteCompartido); a link
 * that opens nothing says so, whatever the reason.
 */
export default function SharedReport({
    company,
    expiresAt,
    minimum,
    report,
}: {
    company: string | null;
    expiresAt: string | null;
    /** The minimum of people per group the company applies. */
    minimum: number | null;
    report: ScenarioReport | null;
}) {
    if (report === null) {
        return (
            <>
                <Head title="Enlace no disponible" />
                <div className="flex min-h-svh flex-col bg-background">
                    <header className="px-4 py-5 md:px-10">
                        <CompanyMark className="h-7" />
                    </header>
                    <SharedUnavailable />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Reporte · ${report.scenario.name}`} />
            <ReportLinkBar
                start={<CompanyMark className="h-7" />}
                className="max-w-[1240px] md:px-10"
            >
                <PrintButton variant="outline" label="Imprimir" />
            </ReportLinkBar>
            <main
                data-report-sheet
                className="mx-auto flex min-h-svh w-full max-w-[1240px] flex-col gap-8 px-4 py-4 [page:report] md:px-10 print:gap-6 print:p-0"
            >
                <ReportPrintHeader
                    company={company}
                    scenario={report.scenario.name}
                />
                <ReportLinkNotice>
                    {sharedNotice(report, company, { expiresAt, minimum })}
                </ReportLinkNotice>
                <CompensationHeader
                    eyebrow="Reporte de impacto · Compensación"
                    title={report.scenario.name}
                    lead={reportSubtitle(report, company)}
                />
                <ReportBody
                    report={report}
                    union={false}
                    shared
                    stickyClassName="top-0"
                />
            </main>
        </>
    );
}
