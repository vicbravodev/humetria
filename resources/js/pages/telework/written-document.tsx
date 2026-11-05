import { Head, setLayoutProps } from '@inertiajs/react';
import PrintButton from '@/components/humetria/print-button';
import WrittenSheet from '@/components/telework/written-sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { teleworkCrumbs } from '@/lib/telework/crumbs';
import { index as documents } from '@/routes/tenant/telework/documents';
import type { WrittenDocument } from '@/types';

/** A version of a NOM-037 document written with questions (decisión A3), to read and print. */
export default function TeleworkWrittenDocument({
    document,
}: {
    document: WrittenDocument;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(
            tenantSlug,
            null,
            { title: 'Documentos', href: documents(tenantSlug) },
            { title: `${document.title} · versión ${document.version}` },
        ),
    });

    return (
        <>
            <Head title={`${document.title} · versión ${document.version}`} />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex justify-end print:hidden">
                    <PrintButton />
                </div>
                <WrittenSheet document={document} />
            </div>
        </>
    );
}
