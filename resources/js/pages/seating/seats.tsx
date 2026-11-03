import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import SeatForm from '@/components/seating/seat-form';
import SeatsTable from '@/components/seating/seats-table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center, index } from '@/routes/tenant/seating';
import type {
    Paginated,
    SeatEditing,
    SeatFeatureOption,
    SeatOption,
    SeatRow,
    Shortfall,
} from '@/types';

export default function SeatingSeats({
    workCenter,
    seats,
    editing,
    shortfalls,
    shortfallNote,
    canManage,
    kinds,
    uses,
    conditions,
    features,
}: {
    workCenter: { id: string; name: string };
    seats: Paginated<SeatRow>;
    editing: SeatEditing | null;
    shortfalls: Shortfall[];
    shortfallNote: string;
    canManage: boolean;
    kinds: SeatOption[];
    uses: SeatOption[];
    conditions: SeatOption[];
    features: SeatFeatureOption[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            {
                title: workCenter.name,
                href: center([tenantSlug, workCenter.id]),
            },
            { title: 'Asientos' },
        ],
    });

    return (
        <>
            <Head title={`Asientos · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Asientos"
                    facts={[{ icon: Factory, text: workCenter.name }]}
                />
                {shortfalls.length > 0 && (
                    <Notice>
                        <strong>
                            {shortfalls.length === 1
                                ? '1 puesto parece tener menos asientos que personas a la vez:'
                                : `${shortfalls.length} puestos parecen tener menos asientos que personas a la vez:`}
                        </strong>{' '}
                        {shortfalls
                            .map(
                                (item) =>
                                    `${item.name} (${item.seats} de ${item.maxPeopleAtOnce})`,
                            )
                            .join(', ')}
                        . {shortfallNote}
                    </Notice>
                )}
                <SeatsTable
                    workCenterId={workCenter.id}
                    seats={seats}
                    canManage={canManage}
                />
                {canManage && (
                    <div className="max-w-[880px] card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                        <SeatForm
                            workCenterId={workCenter.id}
                            seat={editing}
                            kinds={kinds}
                            uses={uses}
                            conditions={conditions}
                            features={features}
                        />
                    </div>
                )}
            </div>
        </>
    );
}
