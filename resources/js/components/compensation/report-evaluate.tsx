import ReportPart from '@/components/compensation/report-part';
import { EVALUATION_POINTS } from '@/lib/compensation/report-evaluate';

/** «Qué más evaluar»: what a raise is weighed against besides its cost. Guidance to decide with: no figure of ours. */
export default function ReportEvaluate({ title }: { title: string }) {
    return (
        <ReportPart
            id="evaluar"
            title={title}
            description="El costo no es lo único que decide. Antes de cerrar, revisa estos puntos."
        >
            <ul className="grid break-inside-avoid gap-x-8 gap-y-5 card p-5 sm:p-6 md:grid-cols-2">
                {EVALUATION_POINTS.map((point) => (
                    <li key={point.key} className="flex flex-col gap-1">
                        <h3 className="font-semibold">{point.title}</h3>
                        <p className="text-sm text-muted-foreground">
                            {point.detail}
                        </p>
                    </li>
                ))}
            </ul>
        </ReportPart>
    );
}
