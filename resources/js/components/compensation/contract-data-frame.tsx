import type { ReactNode } from 'react';
import CompensationHeader from '@/components/compensation/compensation-header';
import ContractDataNav from '@/components/compensation/contract-data-nav';
import type { ContractStatus, StatusKey } from '@/lib/compensation/start';

/**
 * «Los datos de tu contrato» (board V4-Comp-Datos): the menu of the data at the left and, at the right, the one that is open
 * with its title, what it is and its action.
 */
export default function ContractDataFrame({
    status,
    current,
    title,
    lead,
    action,
    children,
}: {
    status: ContractStatus;
    current: StatusKey;
    title: string;
    lead: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            <CompensationHeader
                eyebrow="Compensación"
                title="Los datos de tu contrato"
                lead="El punto de partida de cada escenario. Captúralos una vez; los escenarios se recalculan solos."
            />
            <div className="grid items-start gap-8 lg:grid-cols-[240px_minmax(0,1fr)]">
                <ContractDataNav status={status} current={current} />
                <section
                    aria-labelledby="contract-data-title"
                    className="grid min-w-0 gap-6"
                >
                    <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                        <div className="grid max-w-[640px] gap-1">
                            <h2
                                id="contract-data-title"
                                className="text-xl leading-7 font-semibold"
                            >
                                {title}
                            </h2>
                            <p className="text-muted-foreground">{lead}</p>
                        </div>
                        {action}
                    </div>
                    {children}
                </section>
            </div>
        </>
    );
}
