import DossierMissing from '@/components/telework/dossier-missing';
import { describeDate } from '@/lib/calendar-date';
import type { DossierChecklist, DossierEvidence } from '@/types';

/**
 * 5.4, 7 and 5.5.1: the published list, what it leaves without a question, and how many were answered. The answers and
 * photos stay in the application: the link takes the Comisión to them.
 */
export default function DossierChecklistBlock({
    checklist,
    evidence,
}: {
    checklist: DossierChecklist | null;
    evidence: DossierEvidence;
}) {
    if (checklist === null) {
        return (
            <DossierMissing>
                la Comisión no ha publicado la lista de verificación.
            </DossierMissing>
        );
    }

    return (
        <div className="flex flex-col gap-2 text-[15px] leading-[22px]">
            <p>
                Versión {checklist.version} publicada el{' '}
                {describeDate(checklist.publishedOn ?? '') ?? '—'}.
            </p>
            {checklist.gaps.length === 0 ? (
                <p>Cubre todos los requisitos de los numerales 5.1.1 y 7.</p>
            ) : (
                checklist.gaps.map((gap) => (
                    <p key={gap.section}>
                        Sección {gap.section} sin pregunta para:{' '}
                        {gap.requirements.join('; ')}.
                    </p>
                ))
            )}
            <p>
                {evidence.checklists} listas contestadas y {evidence.photos}{' '}
                fotos. Las respuestas y las fotos no se imprimen: se consultan
                en la aplicación (
                <a href={evidence.href} className="text-primary underline">
                    listas por revisar
                </a>
                ).
            </p>
        </div>
    );
}
