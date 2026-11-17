import { Head } from '@inertiajs/react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ReportBody from '@/components/compensation/report-body';
import SharedUnavailable from '@/components/compensation/shared-unavailable';
import Logo from '@/components/humetria/logo';
import PrintButton from '@/components/humetria/print-button';
import ReportLinkBar from '@/components/humetria/report-link-bar';
import ReportLinkNotice from '@/components/humetria/report-link-notice';
import { reportSubtitle } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/**
 * The report of a scenario opened from a link that expires (board V4-Comp-Reporte, «compartida por enlace»): the same report,
 * read-only, with the small groups folded, under the common bar of the reports by a link (board ReporteCompartido); a link
 * that opens nothing says so, whatever the reason.
 */
export default function SharedReport({
    company,
    expiresAt,
    report,
}: {
    company: string | null;
    expiresAt: string | null;
    report: ScenarioReport | null;
}) {
    if (report === null) {
        return (
            <>
                <Head title="Enlace no disponible" />
                <div className="flex min-h-svh flex-col bg-background">
                    <header className="px-4 py-5 md:px-10">
                        <Logo className="h-7" />
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
                start={<Logo className="h-7" />}
                className="max-w-[1240px] md:px-10"
            >
                <PrintButton variant="outline" label="Imprimir" />
            </ReportLinkBar>
            <main className="mx-auto flex min-h-svh w-full max-w-[1240px] flex-col gap-8 px-4 py-4 md:px-10 print:p-0">
                <ReportLinkNotice>
                    {company} compartió este reporte contigo. Vence el{' '}
                    {expiresAt}. Los grupos de pocas personas se muestran juntos
                    como «Otros».
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
