import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import DocumentRow from '@/components/telework/document-row';
import DocumentUploadForm from '@/components/telework/document-upload-form';
import DocumentsToolbar from '@/components/telework/documents-toolbar';
import type {
    DocumentFilters,
    DocumentKindOption,
    DocumentRow as Row,
    Paginated,
} from '@/types';

export default function Documents({
    documents,
    filters,
    kinds,
    canManage,
}: {
    documents: Paginated<Row>;
    filters: DocumentFilters;
    kinds: DocumentKindOption[];
    canManage: boolean;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-037' }, { title: 'Documentos' }],
    });

    return (
        <>
            <Head title="Documentos de NOM-037" />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Documentos de NOM-037"
                    description="La política y los demás documentos que pide la norma, en PDF y por centro de trabajo. Cada archivo nuevo es una versión nueva: nada se borra."
                />
                {canManage && (
                    <DocumentUploadForm tenantSlug={tenantSlug} kinds={kinds} />
                )}
                <DocumentsToolbar tenantSlug={tenantSlug} filters={filters} />
                {documents.total === 0 ? (
                    <EmptyState icon={FileText} title="Aún no hay documentos">
                        {canManage
                            ? 'Sube la Política de Teletrabajo para empezar: elige el centro y marca los incisos que cubre.'
                            : 'Cuando RH publique un documento, aparecerá aquí.'}
                    </EmptyState>
                ) : (
                    <ol aria-label="Documentos" className="flex flex-col gap-3">
                        {documents.data.map((row) => (
                            <DocumentRow
                                key={row.id}
                                tenantSlug={tenantSlug}
                                row={row}
                                kind={kinds.find((k) => k.value === row.kind)}
                            />
                        ))}
                    </ol>
                )}
                <ConsolePagination
                    page={documents}
                    label="Páginas de documentos"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
