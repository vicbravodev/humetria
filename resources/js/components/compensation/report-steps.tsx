import ReportPart from '@/components/compensation/report-part';
import { baseFacts, reportSteps } from '@/lib/compensation/report-steps';
import type { ScenarioReport } from '@/types';

/** «El cálculo paso a paso»: from the raise to the cost of the first year, each step with what goes in and what comes out. */
export default function ReportSteps({
    report,
    title,
}: {
    report: ScenarioReport;
    title: string;
}) {
    const average = report.example !== null;
    const base = baseFacts(report);

    return (
        <ReportPart
            id="calculo"
            pageBreak
            title={title}
            description={
                average
                    ? 'Una persona de la plantilla promedio, con el mismo cálculo que usa el total. Así puedes comprobar cada cifra.'
                    : 'Lo que suma cada concepto a la nómina, con el mismo cálculo que usa el total. La plantilla no tiene una persona típica, así que los pasos son sumas.'
            }
        >
            {base !== null && (
                <dl className="flex break-inside-avoid flex-wrap gap-x-10 gap-y-3 card px-5 py-4">
                    {base.map((fact) => (
                        <div
                            key={fact.label}
                            className="flex min-w-[8rem] flex-col gap-0.5"
                        >
                            <dt className="text-caption text-muted-foreground">
                                {fact.label}
                            </dt>
                            <dd className="font-semibold tabular-nums">
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            <ol data-splittable className="flex flex-col divide-y card">
                {reportSteps(report).map((step, index) => (
                    <li
                        key={step.key}
                        className="grid break-inside-avoid gap-x-6 gap-y-3 p-5 md:grid-cols-[13rem_minmax(0,1fr)] print:gap-y-2 print:px-4 print:py-3"
                    >
                        <div className="flex items-start gap-3">
                            <span
                                aria-hidden
                                className="flex size-7 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground tabular-nums"
                            >
                                {index + 1}
                            </span>
                            <h3 className="font-semibold">{step.title}</h3>
                        </div>
                        <div className="flex flex-col gap-3">
                            <dl className="grid gap-1.5 text-sm">
                                {step.lines.map((line) => (
                                    <div
                                        key={line.label}
                                        className="flex justify-between gap-4"
                                    >
                                        <dt className="text-muted-foreground">
                                            {line.label}
                                        </dt>
                                        <dd className="shrink-0 text-right font-medium tabular-nums">
                                            {line.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="flex flex-wrap items-baseline justify-between gap-x-4 rounded-lg bg-muted px-4 py-2.5 print:py-1.5">
                                <span className="text-sm text-muted-foreground">
                                    {step.result.label}
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {step.result.value}
                                </span>
                            </p>
                        </div>
                    </li>
                ))}
            </ol>
        </ReportPart>
    );
}
