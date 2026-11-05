import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import AgreementForm from '@/components/seating/agreement-form';
import AgreementsTable from '@/components/seating/agreements-table';
import MeasureForm from '@/components/seating/measure-form';
import MeasuresTable from '@/components/seating/measures-table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
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
    agreements,
    editing,
    canManage,
    kinds,
    statuses,
    owners,
}: {
    workCenter: { id: string; name: string };
    measures: Paginated<MeasureRow>;
    agreements: Paginated<AgreementRow>;
    editing: MeasureEditing | null;
    canManage: boolean;
    kinds: SeatOption[];
    statuses: SeatOption[];
    owners: SeatOption[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Plan de acción',
        }),
    });

    return (
        <>
            <Head title={`Plan de acción · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-10 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Plan de acción"
                    description="Las medidas para cuidar a quienes trabajan de pie y las pausas que se acordaron. Nada se borra: una medida se cancela."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                />
                <MeasuresTable
                    workCenterId={workCenter.id}
                    measures={measures}
                    canManage={canManage}
                />
                {canManage && (
                    <div className="max-w-[880px] card p-6">
                        <MeasureForm
                            workCenterId={workCenter.id}
                            editing={editing}
                            kinds={kinds}
                            statuses={statuses}
                            owners={owners}
                        />
                    </div>
                )}
                <AgreementsTable agreements={agreements} />
                {canManage && (
                    <div className="max-w-[880px] card p-6">
                        <AgreementForm workCenterId={workCenter.id} />
                    </div>
                )}
            </div>
        </>
    );
}
