import DossierMissing from '@/components/telework/dossier-missing';
import { describeDate } from '@/lib/calendar-date';
import type { DossierDocument } from '@/types';

/** A document in force: version, date, the incisos it covers and, for the ones people confirm, who did. */
export default function DossierDocumentBlock({
    document,
}: {
    document: DossierDocument | undefined;
}) {
    if (document === undefined) {
        return <DossierMissing>aún no se sube este documento.</DossierMissing>;
    }

    return (
        <div className="flex flex-col gap-1 text-[15px] leading-[22px]">
            <p>
                {document.kind}, versión {document.version} del{' '}
                {describeDate(document.publishedOn) ?? '—'} (
                <a href={document.href} className="text-primary underline">
                    PDF
                </a>
                ).
            </p>
            {document.coveredItems.length > 0 && (
                <p>Incisos cubiertos: {document.coveredItems.join('; ')}.</p>
            )}
            {document.missingItems.length > 0 && (
                <p>Incisos sin cubrir: {document.missingItems.join('; ')}.</p>
            )}
            {document.acknowledgements !== null && (
                <p>
                    Acuses de recibo: {document.acknowledgements.acknowledged}{' '}
                    de {document.acknowledgements.expected}.
                </p>
            )}
        </div>
    );
}
