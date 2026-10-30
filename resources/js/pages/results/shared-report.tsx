import { Head } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import LinkUnavailable from '@/components/results/link-unavailable';
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
            <div className="flex min-h-svh flex-col bg-background">
                <header className="flex flex-wrap items-center justify-between gap-4 px-4 py-5 md:px-10 print:hidden">
                    <Logo className="h-7" />
                    {report !== null && <PrintButton />}
                </header>
                {report === null ? (
                    <LinkUnavailable />
                ) : (
                    <main className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 pb-16 md:px-10 print:max-w-none print:p-0">
                        <p className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-card px-4 py-3 text-sm print:hidden">
                            <Link2
                                aria-hidden
                                className="size-[18px] shrink-0 text-primary"
                            />
                            {company} compartió este informe contigo. El enlace
                            vence el {expiresAt}.
                        </p>
                        <ReportDocument report={report} levels={levels} />
                    </main>
                )}
            </div>
        </>
    );
}
