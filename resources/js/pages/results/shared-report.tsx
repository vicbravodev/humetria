import { Head } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Logo from '@/components/humetria/logo';
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
                <main className="mx-auto flex min-h-svh max-w-6xl flex-col gap-8 px-4 py-6 md:px-10 md:py-10">
                    <Logo className="h-7" />
                    <EmptyState
                        icon={Link2Off}
                        title="Este enlace ya no está disponible"
                    >
                        Venció o lo revocaron. Pide uno nuevo a quien te lo
                        compartió.
                    </EmptyState>
                </main>
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
                <p className="text-sm text-muted-foreground print:hidden">
                    {company} compartió este informe contigo. El enlace vence el{' '}
                    {expiresAt}.
                </p>
                <ReportDocument report={report} levels={levels} />
            </main>
        </>
    );
}
