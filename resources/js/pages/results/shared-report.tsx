import { Head } from '@inertiajs/react';
import CompanyMark from '@/components/company-mark';
import PrintButton from '@/components/humetria/print-button';
import ReportLinkBar from '@/components/humetria/report-link-bar';
import ReportLinkNotice from '@/components/humetria/report-link-notice';
import SectionTabs from '@/components/humetria/section-tabs';
import ReportDocument from '@/components/results/report-document';
import ReportLinkUnavailable from '@/components/results/report-link-unavailable';
import { reportSections } from '@/lib/report';
import type { NomReport, RiskLevelInfo } from '@/types';

/**
 * The report of num. 7.7 opened from a temporary link (board ReporteCompartido): the common bar of the reports by a link, the
 * notice of its validity, the title first and the tabs of its parts. What is printed is the report, as everywhere.
 */
export default function SharedReport({
    company,
    expiresAt,
    report,
    levels,
}: {
    company: string | null;
    expiresAt: string | null;
    report: NomReport | null;
    levels: RiskLevelInfo[];
}) {
    if (report === null) {
        return (
            <>
                <Head title="Enlace no disponible" />
                <div className="flex min-h-svh flex-col bg-background">
                    <header className="px-4 py-5 md:px-10">
                        <CompanyMark className="h-10" />
                    </header>
                    <ReportLinkUnavailable />
                </div>
            </>
        );
    }

    return (
        <>
            <Head
                title={company === null ? 'Informe' : `Informe · ${company}`}
            />
            <ReportLinkBar
                start={<CompanyMark className="h-10" />}
                className="max-w-[1120px] md:px-6"
            >
                <PrintButton variant="outline" label="Imprimir" />
            </ReportLinkBar>
            <main className="mx-auto flex min-h-svh w-full max-w-[1120px] flex-col gap-8 px-4 pt-4 pb-24 md:px-6 print:p-0">
                <ReportLinkNotice>
                    {company} compartió este informe contigo. Vence el{' '}
                    {expiresAt}.
                </ReportLinkNotice>
                <ReportDocument
                    report={report}
                    levels={levels}
                    tabs={
                        <SectionTabs
                            label="Secciones del informe"
                            sections={reportSections(report.results.visible)}
                            className="top-0"
                        />
                    }
                />
            </main>
        </>
    );
}
