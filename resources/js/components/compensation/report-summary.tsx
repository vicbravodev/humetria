import ReportFigure from '@/components/compensation/report-figure';
import ReportFindings from '@/components/compensation/report-findings';
import { findings, summaryFigures } from '@/lib/compensation/report';
import type { ScenarioReport } from '@/types';

/** «Resumen»: the four figures a negotiation opens with, and the three findings to read first. */
export default function ReportSummary({
    report,
    union,
}: {
    report: ScenarioReport;
    union: boolean;
}) {
    return (
        <section
            id="resumen"
            aria-label="Resumen"
            className="flex scroll-mt-32 flex-col gap-4"
        >
            <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.35fr_1fr_1fr_1fr]">
                {summaryFigures(report, union).map((figure, index) => (
                    <ReportFigure
                        key={figure.key}
                        figure={figure}
                        index={index}
                    />
                ))}
            </dl>
            <ReportFindings findings={findings(report)} />
        </section>
    );
}
