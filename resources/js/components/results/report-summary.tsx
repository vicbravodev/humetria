import LevelLegend from '@/components/humetria/level-legend';
import KeyFindings from '@/components/results/key-findings';
import ReportCaption from '@/components/results/report-caption';
import ReportKpi from '@/components/results/report-kpi';
import ReportSection from '@/components/results/report-section';
import WaffleChart from '@/components/results/waffle-chart';
import { rankByHighRisk } from '@/lib/report';
import { keyFindings } from '@/lib/report-findings';
import { highRisk, riskVerdict } from '@/lib/results';
import type { NomReport, RiskLevelInfo } from '@/types';

/** The report read in a minute (num. 7.7 e): three figures, every questionnaire by its level, and what stands out. */
export default function ReportSummary({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    const { results, participation, areas } = report;
    const [worst] = rankByHighRisk(results.domains);
    const missing = participation.invited - participation.answered;

    return (
        <ReportSection
            id="resumen"
            title="Resumen ejecutivo"
            aside="7.7 e · Resultados"
        >
            <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                <ReportKpi
                    caption="Participación"
                    value={participation.answered}
                    of={participation.invited}
                    tone="primary"
                    delay={0}
                >
                    {participation.answered} de {participation.invited}{' '}
                    trabajadores contestaron.
                </ReportKpi>
                <ReportKpi
                    caption="En riesgo alto o muy alto"
                    value={highRisk(results.final)}
                    of={results.answered}
                    tone="alarm"
                    delay={1}
                >
                    {highRisk(results.final)} de {results.answered}{' '}
                    cuestionarios en calificación final.
                </ReportKpi>
                {worst !== undefined && (
                    <ReportKpi
                        caption="Dominio más crítico"
                        value={highRisk(worst.levels)}
                        of={results.answered}
                        tone="risk"
                        delay={2}
                    >
                        <b className="font-semibold text-foreground">
                            {worst.label}
                        </b>{' '}
                        · {highRisk(worst.levels)} de {results.answered} en alto
                        o muy alto.
                    </ReportKpi>
                )}
            </div>
            <div
                className="flex break-inside-avoid flex-wrap gap-10 rounded-lg border bg-card p-6 motion-safe:animate-report-rise md:p-8"
                style={{ animationDelay: '320ms' }}
            >
                <div className="flex flex-[1_1_400px] flex-col gap-5">
                    <div className="flex flex-col gap-1.5">
                        <ReportCaption>
                            Calificación final del cuestionario
                        </ReportCaption>
                        <h3 className="text-2xl leading-8 font-semibold">
                            {riskVerdict(results.final)}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Cada cuadro es un cuestionario, calificado por sí
                            solo.
                            {missing > 0 &&
                                ' El cuadro vacío es quien no contestó.'}
                        </p>
                    </div>
                    <WaffleChart
                        final={results.final}
                        missing={missing}
                        levels={levels}
                    />
                    <LevelLegend levels={levels} counts={results.final} />
                </div>
                <div className="flex-[1_1_340px] border-t pt-8 md:border-t-0 md:border-l md:pt-0 md:pl-8">
                    <KeyFindings findings={keyFindings(results, areas)} />
                </div>
            </div>
        </ReportSection>
    );
}
