import PlanMeter from '@/components/tenancy/plan-meter';
import type { PlanUse } from '@/types';

/** What the company uses of the centers and people its plan covers. */
export default function PlanUsage({
    centers,
    people,
}: {
    centers: PlanUse | null;
    people: PlanUse;
}) {
    return (
        <section aria-labelledby="plan-usage" className="grid gap-4">
            <h2
                id="plan-usage"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Lo que usas
            </h2>
            <div className="grid gap-4 sm:grid-cols-2">
                {centers !== null && (
                    <PlanMeter
                        title="Centros de trabajo"
                        use={centers}
                        noun={{ one: 'lugar', many: 'lugares' }}
                    />
                )}
                <PlanMeter
                    title="Personas"
                    use={people}
                    noun={{ one: 'lugar', many: 'lugares' }}
                />
            </div>
        </section>
    );
}
