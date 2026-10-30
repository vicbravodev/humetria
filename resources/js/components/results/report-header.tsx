import Logo from '@/components/humetria/logo';
import { participationText } from '@/lib/results';
import type { NomReport } from '@/types';

/** The top of the report (design system: ReportHeader): who, what, where, when and how many answered. */
export default function ReportHeader({ report }: { report: NomReport }) {
    const facts = [
        { label: 'Centro de trabajo', value: report.center.name },
        { label: 'Periodo', value: report.period },
        {
            label: 'Participación',
            value: participationText(report.participation),
        },
        {
            label: 'Elaboró',
            value: report.responsible?.name ?? 'Pendiente de capturar',
        },
    ];

    return (
        <header className="grid gap-6 border-b pb-8">
            <div className="flex items-start justify-between gap-4">
                <p className="text-lg font-medium">{report.center.legalName}</p>
                <Logo kind="seal" className="h-4" />
            </div>
            <div className="grid gap-1">
                <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                    Diagnóstico de factores de riesgo psicosocial
                </h1>
                <p className="text-muted-foreground">
                    NOM-035-STPS-2018 · {report.guide} · Informe del numeral 7.7
                </p>
            </div>
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {facts.map((fact) => (
                    <div key={fact.label} className="grid gap-1">
                        <dt className="text-sm text-muted-foreground">
                            {fact.label}
                        </dt>
                        <dd className="font-medium">{fact.value}</dd>
                    </div>
                ))}
            </dl>
        </header>
    );
}
