import CenterField from '@/components/complaints-public/center-field';
import InvolvedField from '@/components/complaints-public/step-involved';
import RespondentFields from '@/components/complaints-public/respondent-fields';
import type { Draft } from '@/lib/complaints/form';
import { NOTICE } from '@/lib/complaints/form';
import type { ComplaintKindOption } from '@/types';

/** Step 2 of 4: who is involved, by role and never by name (or, in the notice of an event, its names), and where. */
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
            <div className="flex flex-col gap-1.5">
                <h2 className="text-[22px] leading-7 font-semibold">
                    {draft.type === NOTICE
                        ? '¿Dónde ocurrió y quiénes estuvieron?'
                        : '¿Quién está involucrado?'}
                </h2>
                {draft.type !== NOTICE && (
                    <p className="text-base text-muted-foreground">
                        Así llega a alguien que no tiene que ver con lo que
                        pasó. No le pedimos nombres.
                    </p>
                )}
            </div>
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
