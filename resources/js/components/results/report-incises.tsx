import ReportIncise from '@/components/results/report-incise';
import ReportSection from '@/components/results/report-section';
import ReportWritten from '@/components/results/report-written';
import type { NomReport } from '@/types';

/** The report as num. 7.7 asks for it (section 4): its eight incises, in their order. */
export default function ReportIncises({ report }: { report: NomReport }) {
    const { center, responsible } = report;
    const centerFacts = [
        ['Razón social', `${center.legalName} · RFC ${center.rfc}`],
        ['Domicilio', center.address],
        ['Actividad principal', center.mainActivity],
    ];

    return (
        <ReportSection
            id="informe"
            title="4. Informe conforme al numeral 7.7"
            description="Los ocho apartados que pide la NOM-035-STPS-2018 para el informe de resultados."
            pageBreak
        >
            <div className="rounded-lg border bg-card">
                <ReportIncise code="a" title="Datos del centro de trabajo">
                    <dl className="flex flex-col gap-1">
                        {centerFacts.map(([label, value]) => (
                            <div key={label} className="flex flex-wrap gap-x-3">
                                <dt className="min-w-[140px] text-sm leading-[22px] text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="flex-[1_1_320px]">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </ReportIncise>
                <ReportIncise code="b" title="Objetivo">
                    <ReportWritten text={report.objective} hint="" />
                </ReportIncise>
                <ReportIncise
                    code="c"
                    title="Principales actividades realizadas en el centro"
                >
                    <ReportWritten text={report.activities} hint="" />
                </ReportIncise>
                <ReportIncise code="d" title="Método">
                    <p>{report.method}</p>
                </ReportIncise>
                <ReportIncise code="e" title="Resultados">
                    <p>
                        {report.results.visible
                            ? 'Resumen ejecutivo y secciones 1 a 3 de este informe; detalle completo en el Anexo A.'
                            : `Se recibieron ${report.results.answered} cuestionarios: menos de 5, así que no se muestran resultados para no revelar respuestas.`}
                    </p>
                </ReportIncise>
                <ReportIncise
                    code="f"
                    title="Conclusiones"
                    pending={report.conclusions === null}
                >
                    <ReportWritten
                        text={report.conclusions}
                        hint="Redacta las conclusiones a partir de los focos de la sección 1."
                    />
                </ReportIncise>
                <ReportIncise
                    code="g"
                    title="Recomendaciones y acciones de intervención"
                    pending={report.recommendations === null}
                >
                    <ReportWritten
                        text={report.recommendations}
                        hint="Define acciones de intervención para los dominios de la sección 1."
                    />
                </ReportIncise>
                <ReportIncise
                    code="h"
                    title="Datos del responsable de la evaluación"
                    pending={responsible === null}
                >
                    <ReportWritten
                        text={
                            responsible === null
                                ? null
                                : `${responsible.name}${responsible.license === null ? '' : ` · Cédula profesional ${responsible.license}`}`
                        }
                        hint="Nombre y número de cédula profesional, en su caso."
                    />
                </ReportIncise>
            </div>
        </ReportSection>
    );
}
