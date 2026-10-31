import PortalDocumentCard from '@/components/telework/portal/portal-document-card';
import type { PortalDocument } from '@/types';

/** The documents the company published that the person has to read and confirm. */
export default function PortalDocuments({
    documents,
    token,
}: {
    documents: PortalDocument[];
    token: string;
}) {
    if (documents.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="portal-documents" className="grid gap-3">
            <h2 id="portal-documents" className="text-xl font-semibold">
                Documentos por confirmar
            </h2>
            <ul className="grid gap-3">
                {documents.map((document) => (
                    <PortalDocumentCard
                        key={document.id}
                        document={document}
                        token={token}
                    />
                ))}
            </ul>
        </section>
    );
}
