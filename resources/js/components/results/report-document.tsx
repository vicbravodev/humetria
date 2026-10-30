import ReportHeader from '@/components/results/report-header';
import ReportResults from '@/components/results/report-results';
import ReportSection from '@/components/results/report-section';
import ReportText from '@/components/results/report-text';
import type { NomReport, RiskLevelInfo } from '@/types';

/** The report of num. 7.7, in the order of its incises, as it prints. */
export default function ReportDocument({
    report,
    levels,
}: {
    report: NomReport;
    levels: RiskLevelInfo[];
}) {
    return (
        <article className="grid gap-10 rounded-xl border bg-card p-5 sm:p-10 print:border-0 print:bg-transparent print:p-0">
            <ReportHeader report={report} />
            <ReportSection
                number={1}
                title="Datos del centro de trabajo"
                incise="a"
            >
                <dl className="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Razón social
                        </dt>
                        <dd>
                            {report.center.legalName} · RFC {report.center.rfc}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Domicilio
                        </dt>
                        <dd>{report.center.address}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">
                            Actividad principal
                        </dt>
                        <dd>{report.center.mainActivity}</dd>
                    </div>
                </dl>
            </ReportSection>
            <ReportSection number={2} title="Objetivo" incise="b">
                <ReportText text={report.objective} />
            </ReportSection>
            <ReportSection
                number={3}
                title="Principales actividades realizadas en el centro"
                incise="c"
            >
                <ReportText text={report.activities} />
            </ReportSection>
            <ReportSection number={4} title="Método" incise="d">
                <p>{report.method}</p>
            </ReportSection>
            <ReportSection number={5} title="Resultados" incise="e">
                <ReportResults
                    results={report.results}
                    areas={report.areas}
                    levels={levels}
                />
            </ReportSection>
            <ReportSection number={6} title="Conclusiones" incise="f">
                <ReportText text={report.conclusions} />
            </ReportSection>
            <ReportSection
                number={7}
                title="Recomendaciones y acciones de intervención"
                incise="g"
            >
                <ReportText text={report.recommendations} />
            </ReportSection>
            <ReportSection
                number={8}
                title="Responsable de la evaluación"
                incise="h"
            >
                <ReportText
                    text={
                        report.responsible === null
                            ? null
                            : `${report.responsible.name}${report.responsible.license === null ? '' : ` · Cédula profesional ${report.responsible.license}`}`
                    }
                />
            </ReportSection>
        </article>
    );
}
