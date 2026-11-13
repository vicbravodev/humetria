import { Head, setLayoutProps } from '@inertiajs/react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ContractStatusCard from '@/components/compensation/contract-status-card';
import FirstTimeSteps from '@/components/compensation/first-time-steps';
import NegotiationTip from '@/components/compensation/negotiation-tip';
import NewScenarioButton from '@/components/compensation/new-scenario-button';
import ScenarioStartList from '@/components/compensation/scenario-start-list';
import { useIsUnion } from '@/hooks/use-is-union';
import type { ContractStatus } from '@/lib/compensation/start';
import type { Paginated, ScenarioCounts, ScenarioRow } from '@/types';

/** The start of Compensación (board V4-Comp-Inicio): a company, a union with its own data, or the first time. */
export default function CompensationIndex(props: {
    status: ContractStatus;
    first_time: boolean;
    scenarios: Paginated<ScenarioRow>;
    counts: ScenarioCounts;
    today: { people: number; cost_text: string } | null;
    negotiation: {
        unionized_point_text: string;
        trusted_point_text: string;
    } | null;
}) {
    const union = useIsUnion();
    setLayoutProps({ breadcrumbs: [{ title: 'Compensación' }] });

    return (
        <>
            <Head title="Compensación" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <CompensationHeader
                    eyebrow="Herramientas"
                    title="Compensación"
                    lead="Costea una propuesta de revisión del contrato: cuánto cuesta el primer año, cuánto suma al año al arrancar y con qué supuestos."
                >
                    {!props.first_time && <NewScenarioButton />}
                </CompensationHeader>
                {props.first_time ? (
                    <FirstTimeSteps />
                ) : (
                    <ContractStatusCard status={props.status} />
                )}
                {!props.first_time && (
                    <ScenarioStartList
                        scenarios={props.scenarios}
                        counts={props.counts}
                        today={props.today}
                    />
                )}
                {props.negotiation !== null && props.counts.active > 0 && (
                    <NegotiationTip
                        unionized={props.negotiation.unionized_point_text}
                        trusted={props.negotiation.trusted_point_text}
                    />
                )}
                <p className="text-caption text-muted-foreground">
                    {union
                        ? 'La empresa no ve tus datos ni tus propuestas. Cuando quieras, comparte un reporte con un enlace que vence.'
                        : 'Tus datos de compensación solo los ve quien tú invites. Puedes compartir un reporte con un enlace que vence.'}
                </p>
            </div>
        </>
    );
}
