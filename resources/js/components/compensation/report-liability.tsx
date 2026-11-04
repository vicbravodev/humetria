import ScenarioSection from '@/components/compensation/scenario-section';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

/** The seniority premium the proposal moves, and what a point of salary and one point more or less cost. */
export default function ReportLiability({
    report,
}: {
    report: ScenarioReport;
}) {
    const { seniority, sensitivity } = report;
    const premium = [
        ['Antes', seniority.before_text],
        ['Después', seniority.after_text],
        ['Cambio', seniority.delta_text],
    ] as const;
    const points = [
        ['Un punto de salario', sensitivity.one_point_text],
        ['La propuesta un punto abajo', sensitivity.minus_text],
        ['La propuesta', sensitivity.base_text],
        ['La propuesta un punto arriba', sensitivity.plus_text],
    ] as const;

    return (
        <>
            <ScenarioSection
                title="Prima de antigüedad"
                description={`Estimación de lo que se debería a hoy a ${countOf(seniority.people, 'persona', 'personas')} con 15 años de servicio o más: 12 días por año con el salario topado a dos mínimos. Es un piso del pasivo, no una valuación actuarial (NIF D-3).`}
            >
                <dl className="grid gap-3 sm:grid-cols-3">
                    {premium.map(([label, value]) => (
                        <div
                            key={label}
                            className="grid gap-1 rounded-lg border p-4"
                        >
                            <dt className="text-meta text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-xl font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </ScenarioSection>
            <ScenarioSection
                title="Cuánto vale un punto"
                description="Costo del primer año, con retroactivo. El punto de salario es un 1 % más con el contrato como está; el resto mueve cada aumento de la propuesta un punto."
            >
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {points.map(([label, value]) => (
                        <div
                            key={label}
                            className="grid gap-1 rounded-lg border p-4"
                        >
                            <dt className="text-meta text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-xl font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </ScenarioSection>
        </>
    );
}
