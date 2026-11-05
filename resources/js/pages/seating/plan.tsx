import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory, Plus } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import AgreementForm from '@/components/seating/agreement-form';
import AgreementsTable from '@/components/seating/agreements-table';
import MeasureForm from '@/components/seating/measure-form';
import MeasuresBoard from '@/components/seating/measures-board';
import MeasuresRuleLine from '@/components/seating/measures-rule-line';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type { MeasuresRule } from '@/lib/seating/plan';
import type {
    AgreementRow,
    MeasureEditing,
    MeasureRow,
    Paginated,
    SeatOption,
} from '@/types';

export default function SeatingPlan({
    workCenter,
    measures,
    measuresRule,
    agreements,
    withoutAgreement,
    editing,
    canManage,
    kinds,
    statuses,
    owners,
}: {
    workCenter: { id: string; name: string };
    measures: Paginated<MeasureRow>;
    measuresRule: MeasuresRule;
    agreements: Paginated<AgreementRow>;
    withoutAgreement: string[];
    editing: MeasureEditing | null;
    canManage: boolean;
    kinds: SeatOption[];
    statuses: SeatOption[];
    owners: SeatOption[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Plan',
        }),
    });

    return (
        <>
            <Head title={`Plan de medidas y pausas · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-10 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Plan de medidas y pausas"
                    description="Lo que haces por quien trabaja de pie. Nada se borra: una medida se cancela y queda en el historial."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                >
                    {canManage && (
                        <Button asChild>
                            <a href="#registrar-medida">
                                <Plus aria-hidden />
                                Registrar medida
                            </a>
                        </Button>
                    )}
                </PageHeader>
                <MeasuresRuleLine rule={measuresRule} />
                <MeasuresBoard
                    workCenterId={workCenter.id}
                    measures={measures}
                    canManage={canManage}
                />
                {canManage && (
                    <div
                        id="registrar-medida"
                        className="max-w-[880px] scroll-mt-6 card p-6"
                    >
                        <MeasureForm
                            workCenterId={workCenter.id}
                            editing={editing}
                            kinds={kinds}
                            statuses={statuses}
                            owners={owners}
                        />
                    </div>
                )}
                <AgreementsTable
                    agreements={agreements}
                    missing={withoutAgreement}
                    action={canManage ? '#registrar-acuerdo' : null}
                />
                {canManage && (
                    <div
                        id="registrar-acuerdo"
                        className="max-w-[880px] scroll-mt-6 card p-6"
                    >
                        <AgreementForm workCenterId={workCenter.id} />
                    </div>
                )}
            </div>
        </>
    );
}
