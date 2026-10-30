import { Head } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Logo from '@/components/humetria/logo';
import PrintButton from '@/components/results/print-button';
import ReportDocument from '@/components/results/report-document';
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
    return (
        <>
            <Head
                title={
                    report === null
                        ? 'Enlace no disponible'
                        : `Informe · ${company ?? ''}`
                }
            />
            <main className="mx-auto flex min-h-svh max-w-6xl flex-col gap-8 px-4 py-6 md:px-10 md:py-10 print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-4 print:hidden">
                    <Logo className="h-7" />
                    {report !== null && <PrintButton />}
                </div>
                {report === null ? (
                    <EmptyState
                        icon={Link2Off}
                        title="Este enlace ya no está disponible"
                    >
                        Venció o lo revocaron. Pide uno nuevo a quien te lo
                        compartió.
                    </EmptyState>
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground print:hidden">
                            {company} compartió este informe contigo. El enlace
                            vence el {expiresAt}.
                        </p>
                        <ReportDocument report={report} levels={levels} />
                    </>
                )}
            </main>
        </>
    );
}
