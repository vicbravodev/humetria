import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import DocumentForm from '@/components/seating/document-form';
import DocumentsTable from '@/components/seating/documents-table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { center, index } from '@/routes/tenant/seating';
import type { SeatingDocumentRow, Paginated, SeatOption } from '@/types';

export default function SeatingDocuments({
    workCenter,
    documents,
    canManage,
    kinds,
    incisos,
}: {
    workCenter: { id: string; name: string };
    documents: Paginated<SeatingDocumentRow>;
    canManage: boolean;
    kinds: SeatOption[];
    incisos: SeatOption[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            {
                title: workCenter.name,
                href: center([tenantSlug, workCenter.id]),
            },
            { title: 'Documentos' },
        ],
    });

    return (
        <>
            <Head title={`Documentos · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Documentos"
                    description="Lo que muestras en una inspección: el reglamento, las actas de la Comisión y el diagnóstico y programa. Solo PDF; nada se borra."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                />
                <DocumentsTable documents={documents} />
                {canManage && (
                    <div className="max-w-[880px] card p-6">
                        <DocumentForm
                            workCenterId={workCenter.id}
                            kinds={kinds}
                            incisos={incisos}
                        />
                    </div>
                )}
            </div>
        </>
    );
}
