import { Head } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ReportBody from '@/components/compensation/report-body';
import SharedUnavailable from '@/components/compensation/shared-unavailable';
import Logo from '@/components/humetria/logo';
import { useLightPrint } from '@/hooks/use-light-print';
import { reportSubtitle } from '@/lib/compensation/report-notes';
import type { ScenarioReport } from '@/types';

/**
 * The report of a scenario opened from a link that expires (board V4-Comp-Reporte, «compartida por enlace»): the same report,
 * read-only, with the small groups folded; a link that opens nothing says so, whatever the reason.
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
            <main className="mx-auto flex min-h-svh w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 print:p-0">
                <Logo className="h-7 print:hidden" />
                <p className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3 text-sm text-accent-foreground print:hidden">
                    <Link2
                        aria-hidden
                        className="mt-0.5 size-[18px] shrink-0 text-primary"
                    />
                    <span>
                        {company} compartió este reporte contigo. El enlace
                        vence el {expiresAt}. Los grupos de pocas personas se
                        muestran juntos como «Otros».
                    </span>
                </p>
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
