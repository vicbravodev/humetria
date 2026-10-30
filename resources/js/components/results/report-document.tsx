import Logo from '@/components/humetria/logo';
import ReportAnnex from '@/components/results/report-annex';
import ReportAreas from '@/components/results/report-areas';
import ReportDomains from '@/components/results/report-domains';
import ReportFocus from '@/components/results/report-focus';
import ReportHeader from '@/components/results/report-header';
import ReportIncises from '@/components/results/report-incises';
import ReportSummary from '@/components/results/report-summary';
import { useLightPrint } from '@/hooks/use-light-print';
import type { NomReport, RiskLevelInfo } from '@/types';

/**
 * The report of num. 7.7 as it reads and prints: the summary, where to act first, the categories and domains, the
 * areas, the eight incises and the annexes. With fewer than five questionnaires, only the incises and what each level
 * needs. Always printed in the light theme.
 */
export default function ReportDocument({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    useLightPrint();

    return (
        <article className="flex flex-col gap-12">
            <ReportHeader report={report} />
            {report.results.visible && (
                <>
                    <ReportSummary report={report} levels={levels} />
                    <ReportFocus report={report} levels={levels} />
                    <ReportDomains report={report} levels={levels} />
                    <ReportAreas report={report} />
                </>
            )}
            <ReportIncises report={report} />
            <ReportAnnex report={report} levels={levels} />
            <footer className="flex flex-wrap items-center justify-between gap-3 border-t pt-6 text-[13px] text-muted-foreground">
                <span>
                    Resultados agregados y confidenciales. Ningún dato permite
                    identificar a una persona.
                </span>
                <Logo kind="seal" className="h-4" />
            </footer>
        </article>
    );
}
