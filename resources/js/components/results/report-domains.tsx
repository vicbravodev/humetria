import LevelLegend from '@/components/humetria/level-legend';
import DivergingRow from '@/components/results/diverging-row';
import ReportCaption from '@/components/results/report-caption';
import ReportSection from '@/components/results/report-section';
import { divergingScale, nestDomains } from '@/lib/report';
import type { NomReport, RiskGroup, RiskLevelInfo } from '@/types';

type Row = { group: RiskGroup; kind: 'category' | 'domain' };

/** Results by category and domain (section 2): each category, then its domains, on both sides of the axis. */
export default function ReportDomains({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    const { results } = report;
    const rows: Row[] = nestDomains(
        results.categories,
        results.domains,
    ).flatMap(({ category, domains }): Row[] => [
        { group: category, kind: 'category' },
        ...domains.map((domain): Row => ({ group: domain, kind: 'domain' })),
    ]);
    const scale = divergingScale(rows.map((row) => row.group));

    return (
        <ReportSection
            id="dominios"
            title="2. Resultados por categoría y dominio"
            aside={`${results.answered} cuestionarios`}
            description="A la derecha del eje, los cuestionarios en riesgo alto o muy alto; a la izquierda, el resto. Mientras más larga la barra derecha, más urgente."
            pageBreak
        >
            <div className="break-inside-avoid rounded-lg border bg-card px-4 pt-6 pb-5 md:px-7">
                <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_56px] items-end border-b pb-3 md:grid-cols-[300px_minmax(0,1fr)_minmax(0,1fr)_72px]">
                    <span className="hidden md:block">
                        <ReportCaption>Categoría y dominio</ReportCaption>
                    </span>
                    <span className="pr-2.5 text-right text-[13px] text-muted-foreground">
                        ← Nulo, bajo o medio
                    </span>
                    <span className="pl-2.5 text-[13px] font-semibold text-risk-muy-alto-fg">
                        Alto o muy alto →
                    </span>
                    <span className="text-right">
                        <ReportCaption>% alto</ReportCaption>
                    </span>
                </div>
                <div className="flex flex-col py-2">
                    {rows.map((row, index) => (
                        <DivergingRow
                            key={`${row.kind}-${row.group.key}`}
                            group={row.group}
                            levels={levels}
                            layout={{ kind: row.kind, scale, index }}
                        />
                    ))}
                </div>
                <div className="flex flex-wrap justify-between gap-3 border-t pt-4">
                    <LevelLegend
                        levels={levels}
                        className="gap-x-[18px] text-[13px] text-muted-foreground"
                    />
                    <span className="text-[13px] text-muted-foreground">
                        Escala de 0 a {scale} % por lado. Pasa el cursor sobre
                        una barra para ver el detalle.
                    </span>
                </div>
            </div>
            <p className="max-w-[820px] text-[13px] leading-[18px] text-muted-foreground">
                Cada categoría se califica con su propia escala ({report.guide},
                numeral {report.guide.replace('Guía ', '')}.3). Por eso su
                porcentaje puede no coincidir con el de sus dominios.
            </p>
        </ReportSection>
    );
}
