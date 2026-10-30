import { Head } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import LinkUnavailable from '@/components/results/link-unavailable';
import PrintButton from '@/components/results/print-button';
import ReportBar from '@/components/results/report-bar';
import ReportDocument from '@/components/results/report-document';
import { reportSections } from '@/lib/report';
import type { NomReport, RiskLevelInfo } from '@/types';

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
                        <Logo className="h-7" />
                    </header>
                    <LinkUnavailable />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Informe · ${company ?? ''}`} />
            <ReportBar
                className="md:top-0"
                sections={reportSections(report.results.visible)}
                start={<Logo className="h-7" />}
            >
                <PrintButton />
            </ReportBar>
            <main className="mx-auto flex min-h-svh w-full max-w-[1120px] flex-col gap-12 px-4 pt-6 pb-24 md:px-6 print:p-0">
                <p className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-card px-4 py-3 text-sm print:hidden">
                    <Link2
                        aria-hidden
                        className="size-[18px] shrink-0 text-primary"
                    />
                    {company} compartió este informe contigo. El enlace vence el{' '}
                    {expiresAt}.
                </p>
                <ReportDocument report={report} levels={levels} />
            </main>
        </>
    );
}
