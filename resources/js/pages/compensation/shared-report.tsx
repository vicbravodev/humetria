import { Head } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import ReportAssumptions from '@/components/compensation/report-assumptions';
import ReportConcepts from '@/components/compensation/report-concepts';
import ReportCoverage from '@/components/compensation/report-coverage';
import ReportCuts from '@/components/compensation/report-cuts';
import ReportHeadline from '@/components/compensation/report-headline';
import ReportLiability from '@/components/compensation/report-liability';
import ReportTimeline from '@/components/compensation/report-timeline';
import ReportWarnings from '@/components/compensation/report-warnings';
import SharedUnavailable from '@/components/compensation/shared-unavailable';
import Logo from '@/components/humetria/logo';
import PageHeader from '@/components/humetria/page-header';
import PrintButton from '@/components/humetria/print-button';
import { useLightPrint } from '@/hooks/use-light-print';
import { raiseSummary } from '@/lib/compensation/scenarios';
import type { ScenarioReport } from '@/types';

export default function SharedReport({
    company,
    expiresAt,
    report,
}: {
    company: string | null;
    expiresAt: string | null;
    report: ScenarioReport | null;
}) {
    useLightPrint();

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
            <main className="mx-auto flex min-h-svh w-full max-w-[1120px] flex-col gap-8 px-4 py-6 md:px-6 print:p-0">
                <Logo className="h-7 print:hidden" />
                <p className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-card px-4 py-3 text-sm print:hidden">
                    <Link2
                        aria-hidden
                        className="size-[18px] shrink-0 text-primary"
                    />
                    {company} compartió este reporte contigo. Los grupos de
                    pocas personas se muestran juntos como «Otros». El enlace
                    vence el {expiresAt}.
                </p>
                <PageHeader
                    caption="Reporte de impacto"
                    title={report.scenario.name}
                    description={`Desde el ${report.scenario.effective_on} · ${raiseSummary(report.scenario.unionized_raise, report.scenario.trusted_raise)}.`}
                >
                    <span className="print:hidden">
                        <PrintButton />
                    </span>
                </PageHeader>
                <ReportCoverage report={report} />
                <ReportWarnings report={report} />
                <ReportHeadline report={report} />
                <ReportConcepts concepts={report.concepts} />
                <ReportCuts cuts={report.cuts} />
                <ReportTimeline report={report} />
                <ReportLiability report={report} />
                <ReportAssumptions assumptions={report.assumptions} />
            </main>
        </>
    );
}
