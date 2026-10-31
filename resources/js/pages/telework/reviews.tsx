import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import ReviewRow from '@/components/telework/review-row';
import { show } from '@/routes/tenant/telework/committee';
import type { Paginated, ReviewRow as Row } from '@/types';

export default function Reviews({
    workCenter,
    applications,
}: {
    workCenter: { id: string; name: string };
    applications: Paginated<Row>;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037' },
            {
                title: 'Comisión',
                href: show({ tenant: tenantSlug, workCenter: workCenter.id }),
            },
            { title: 'Listas por revisar' },
        ],
    });

    return (
        <>
            <Head title={`Listas por revisar · ${workCenter.name}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption={workCenter.name}
                    title="Listas por revisar"
                    description={
                        applications.total === 0
                            ? 'No hay listas esperando a la Comisión.'
                            : `${applications.total} ${applications.total === 1 ? 'lista espera' : 'listas esperan'} a la Comisión, la más antigua primero.`
                    }
                />
                {applications.total === 0 ? (
                    <EmptyState icon={ClipboardCheck} title="Todo al día">
                        Cuando una persona envíe la lista de verificación de su
                        lugar de trabajo, aparecerá aquí para que la revises.
                    </EmptyState>
                ) : (
                    <ol aria-label="Listas" className="flex flex-col gap-3">
                        {applications.data.map((row) => (
                            <ReviewRow
                                key={row.id}
                                tenantSlug={tenantSlug}
                                row={row}
                            />
                        ))}
                    </ol>
                )}
                <ConsolePagination
                    page={applications}
                    label="Páginas de listas"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
