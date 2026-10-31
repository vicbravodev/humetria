import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import CenterCard from '@/components/telework/center-card';
import type { CenterCard as Card, Paginated } from '@/types';

export default function TeleworkIndex({
    centers,
}: {
    centers: Paginated<Card>;
}) {
    return (
        <>
            <Head title="NOM-037" />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="NOM-037"
                    description={
                        <span className="inline-block max-w-[620px]">
                            Teletrabajo: condiciones de seguridad y salud. Cada
                            centro de trabajo lleva su expediente, con el avance
                            de la tabla de inspección.
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
                            NOM-037 se lleva por centro de trabajo. Cuando haya
                            uno a tu cargo, aparecerá aquí.
                        </EmptyState>
                    ) : (
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(440px,100%),1fr))] gap-5">
                            {centers.data.map((card) => (
                                <CenterCard key={card.id} card={card} />
                            ))}
                        </div>
                    )}
                    <ConsolePagination
                        page={centers}
                        label="Páginas de centros de trabajo"
                        previous="Anteriores"
                        next="Siguientes"
                        pinned={false}
                    />
                </section>
            </div>
        </>
    );
}

TeleworkIndex.layout = {
    breadcrumbs: [{ title: 'Cumplimiento' }, { title: 'NOM-037' }],
};
