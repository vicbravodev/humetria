import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import CenterCard from '@/components/seating/center-card';
import type { Paginated, SeatingCenterCard } from '@/types';

export default function SeatingIndex({
    centers,
}: {
    centers: Paginated<SeatingCenterCard>;
}) {
    return (
        <>
            <Head title="Ley Silla" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Ley Silla"
                    description={
                        <span className="inline-block max-w-[620px]">
                            Asientos con respaldo y descansos para quien trabaja
                            de pie. Cada centro de trabajo lleva su expediente.
                        </span>
                    }
                />
                <section
                    aria-label="Centros de trabajo"
                    className="flex flex-col gap-4"
                >
                    {centers.total === 0 ? (
                        <EmptyState
                            icon={Building2}
                            title="Sin centros de trabajo"
                        >
                            Ley Silla se lleva por centro de trabajo. Cuando
                            haya uno a tu cargo, aparecerá aquí.
                        </EmptyState>
                    ) : (
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(440px,100%),1fr))] gap-5">
                            {centers.data.map((card) => (
                                <CenterCard key={card.id} card={card} />
                            ))}
                        </div>
                    )}
                    <Pagination
                        page={centers}
                        label="Páginas de centros de trabajo"
                        pinned={false}
                    />
                </section>
            </div>
        </>
    );
}

SeatingIndex.layout = {
    breadcrumbs: [{ title: 'Cumplimiento' }, { title: 'Ley Silla' }],
};
