import CaseSection from '@/components/complaints/case-section';
import type { CaseDetail } from '@/types';

/** What the person wrote, as they wrote it: the only place where it reaches a screen. */
export default function CaseWords({ detail }: { detail: CaseDetail }) {
    return (
        <div className="flex flex-col gap-6 card p-6">
            <CaseSection
                title="Lo que cuenta la persona"
                text={detail.description}
            />
            {detail.respondentText !== null && (
                <CaseSection
                    title="Sobre la persona señalada"
                    text={detail.respondentText}
                />
            )}
            {detail.involvedText !== null && (
                <CaseSection
                    title="Personas involucradas"
                    text={detail.involvedText}
                />
            )}
        </div>
    );
}
