import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import Pagination from '@/components/humetria/pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import ReviewRow from '@/components/telework/review-row';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { pendingReviewsText } from '@/lib/telework/review';
import { show } from '@/routes/tenant/telework/committee';
import type { Paginated, ReviewRow as Row } from '@/types';

export default function Reviews({
    workCenter,
    applications,
}: {
    workCenter: { id: string; name: string };
    applications: Paginated<Row>;
}) {
    const tenantSlug = useTenantSlug();
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
                    description={pendingReviewsText(applications.total)}
                />
                {applications.total === 0 ? (
                    <EmptyState icon={ClipboardCheck} title="Todo al día">
                        Cuando una persona envíe la lista de verificación de su
                        lugar de trabajo, aparecerá aquí para que la revises.
                    </EmptyState>
                ) : (
                    <ol aria-label="Listas" className="flex flex-col gap-3">
                        {applications.data.map((row) => (
                            <ReviewRow key={row.id} row={row} />
                        ))}
                    </ol>
                )}
                <Pagination page={applications} label="Páginas de listas" />
            </div>
        </>
    );
}
