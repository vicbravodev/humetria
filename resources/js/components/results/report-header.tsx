import Logo from '@/components/humetria/logo';
import ReportCaption from '@/components/results/report-caption';
import type { NomReport } from '@/types';

/** The top of the report (design system: ReportHeader): what it is, of whom, and its record card. */
export default function ReportHeader({ report }: { report: NomReport }) {
    const facts = [
        { label: 'Periodo de aplicación', value: report.period },
        {
            label: 'Cuestionarios',
            value: `${report.participation.answered} de ${report.participation.invited} trabajadores`,
        },
        {
            label: 'Instrumento',
            value: `${report.guide} · ${report.itemCount} reactivos`,
        },
        { label: 'Elaboró', value: report.responsible?.name ?? null },
    ];

    return (
        <header className="flex flex-col gap-8 pt-4 motion-safe:animate-report-rise">
            <div className="flex justify-end">
                <Logo kind="seal" className="h-4" />
            </div>
            <div className="flex flex-col gap-2.5">
                <p className="text-[13px] leading-[18px] font-medium tracking-[0.06em] text-primary uppercase">
                    NOM-035-STPS-2018 ·{' '}
                    {report.guide.replace('Guía', 'Guía de referencia')} ·
                    Numeral 7.7
                </p>
                <h1 className="max-w-[820px] text-4xl leading-[44px] font-semibold tracking-[-0.02em] md:text-[44px] md:leading-[52px]">
                    Diagnóstico de factores de riesgo psicosocial
                </h1>
                <p className="text-lg leading-7 text-muted-foreground">
                    {report.center.legalName} · {report.center.name}
                </p>
            </div>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] border-y">
                {facts.map((fact) => (
                    <div
                        key={fact.label}
                        className="flex flex-col gap-1 py-4 pr-4"
                    >
                        <dt>
                            <ReportCaption>{fact.label}</ReportCaption>
                        </dt>
                        <dd
                            className={
                                fact.value === null
                                    ? 'font-medium text-muted-foreground'
                                    : 'font-medium'
                            }
                        >
                            {fact.value ?? 'Pendiente de capturar'}
                        </dd>
                    </div>
                ))}
            </dl>
        </header>
    );
}
