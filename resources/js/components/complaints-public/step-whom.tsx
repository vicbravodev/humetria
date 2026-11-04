import CenterField from '@/components/complaints-public/center-field';
import InvolvedField from '@/components/complaints-public/step-involved';
import RespondentFields from '@/components/complaints-public/respondent-fields';
import type { Draft } from '@/lib/complaints/form';
import { NOTICE } from '@/lib/complaints/form';
import type { ComplaintKindOption } from '@/types';

/** Step 3: where it happened and against whom (or, in the notice of an event, who was involved). */
export default function WhomStep({
    code,
    kinds,
    draft,
    onChange,
}: {
    code: string;
    kinds: ComplaintKindOption[];
    draft: Draft;
    onChange: (changes: Partial<Draft>) => void;
}) {
    return (
        <div className="flex flex-col gap-6">
            <h2 className="text-xl font-semibold">
                {draft.type === NOTICE
                    ? '¿Dónde ocurrió?'
                    : '¿Dónde y de quién se trata?'}
            </h2>
            <CenterField
                code={code}
                value={draft.workCenter}
                onChange={(workCenter) => onChange({ workCenter })}
            />
            {draft.type === NOTICE ? (
                <InvolvedField
                    value={draft.involvedText}
                    onChange={(involvedText) => onChange({ involvedText })}
                />
            ) : (
                <RespondentFields
                    kinds={kinds}
                    draft={draft}
                    onChange={onChange}
                />
            )}
        </div>
    );
}
