import { Head, setLayoutProps } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import CompareCard from '@/components/compensation/compare-card';
import CompareDistance from '@/components/compensation/compare-distance';
import CompensationHeader from '@/components/compensation/compensation-header';
import ScenarioCompareTable from '@/components/compensation/scenario-compare-table';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/compensation';
import type {
    CompareDistance as Distance,
    CompareMidpoint,
    CompareMoves,
    CompareRow,
    CompareScenario,
    CompareTotals,
} from '@/types';

/** «Comparar escenarios» (board V4-Comp-Comparar): from the cheapest, the distance to negotiate and each concept. */
export default function ScenarioCompare(props: {
    scenarios: CompareScenario[];
    rows: CompareRow[];
    totals: CompareTotals;
    distance: Distance;
    moves: CompareMoves;
    midpoint: CompareMidpoint | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Compensación', href: index(tenantSlug) },
            { title: 'Comparar' },
        ],
    });

    return (
        <>
            <Head title="Comparar escenarios" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <CompensationHeader
                    eyebrow="Compensación"
                    title="Comparar escenarios"
                    lead="Ordenados del más barato al más caro. Abajo, lo que separa a los dos más caros: la distancia a negociar."
                >
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.print()}
                        className="print:hidden"
                    >
                        <Printer aria-hidden />
                        Imprimir comparación
                    </Button>
                </CompensationHeader>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
                    {props.scenarios.map((scenario) => (
                        <CompareCard key={scenario.id} scenario={scenario} />
                    ))}
                </div>
                <CompareDistance
                    distance={props.distance}
                    moves={props.moves}
                    midpoint={props.midpoint}
                />
                <ScenarioCompareTable
                    scenarios={props.scenarios}
                    rows={props.rows}
                    totals={props.totals}
                />
            </div>
        </>
    );
}
