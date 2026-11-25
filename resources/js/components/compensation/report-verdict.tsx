import ReportFigure from '@/components/compensation/report-figure';
import { summaryFigures, verdictSentence } from '@/lib/compensation/report';
import { openCaveats } from '@/lib/compensation/report-caveats';
import { countOf, plural } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

/** «Resumen»: the answer in a sentence and the four anchors a negotiation opens with, and a pointer to what can move the number. */
export default function ReportVerdict({
    report,
    union,
}: {
    report: ScenarioReport;
    union: boolean;
}) {
    const [cost, against] = verdictSentence(report, union);
    const open = openCaveats(report);

    return (
        <section
            id="resumen"
            aria-label="Resumen"
            className="flex scroll-mt-32 break-inside-avoid flex-col overflow-hidden card"
        >
            <div className="flex flex-col gap-2 p-5 sm:p-7">
                <p className="text-caption font-semibold tracking-[0.06em] text-primary uppercase">
                    Lo que cuesta este aumento
                </p>
                <p className="max-w-[900px] text-xl leading-8 font-semibold tracking-[-0.01em] sm:text-2xl sm:leading-9">
                    {cost}
                </p>
                {against !== undefined && (
                    <p className="max-w-[900px] text-muted-foreground">
                        {against}
                    </p>
                )}
            </div>
            <dl className="grid gap-px border-t bg-border sm:grid-cols-2 xl:grid-cols-4">
                {summaryFigures(report, union).map((figure, index) => (
                    <ReportFigure
                        key={figure.key}
                        figure={figure}
                        index={index}
                    />
                ))}
            </dl>
            {open > 0 && (
                <p className="border-t px-5 py-3 text-sm text-muted-foreground sm:px-7">
                    El número tiene {countOf(open, 'salvedad', 'salvedades')}{' '}
                    {plural(open, 'que lo puede mover', 'que lo pueden mover')}.{' '}
                    <a
                        href="#supuestos"
                        className="touch-hit relative font-medium text-primary underline-offset-4 hover:underline print:hidden"
                    >
                        Ver supuestos y salvedades
                    </a>
                </p>
            )}
        </section>
    );
}
