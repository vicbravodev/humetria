import ReportPart from '@/components/compensation/report-part';
import WaterfallChart from '@/components/humetria/waterfall-chart';
import { waterfallBars } from '@/lib/compensation/report-charts';
import type { ScenarioReport } from '@/types';

/** «De dónde sale el costo»: the cascade of the first year, a bar per concept stacked to the total. */
export default function ReportCost({
    report,
    title,
}: {
    report: ScenarioReport;
    title: string;
}) {
    return (
        <ReportPart
            id="costo"
            title={title}
            description="Cada barra es lo que suma un concepto al primer año. Se apilan de izquierda a derecha hasta el total."
            hint="Pasa el cursor sobre una barra"
        >
            <div className="break-inside-avoid card p-4 sm:p-6">
                <WaterfallChart
                    label="Cascada del costo del primer año por concepto"
                    bars={waterfallBars(report)}
                />
            </div>
            <p className="text-caption text-muted-foreground">
                Las cuotas del IMSS incluyen enfermedad y maternidad, invalidez
                y vida, guarderías, riesgo de trabajo, retiro y cesantía. La
                cuota fija no cambia porque no depende del salario.
            </p>
        </ReportPart>
    );
}
