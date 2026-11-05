import { useState } from 'react';
import type { ReactNode } from 'react';
import AssessmentChecklist from '@/components/seating/assessment-checklist';
import AssessmentForm from '@/components/seating/assessment-form';
import AssessmentScoreCard from '@/components/seating/assessment-score-card';
import { scoreSummary } from '@/lib/seating/assessment';
import type { AssessmentCondition, AssessmentRecord } from '@/types';

/**
 * The assessment of a person (board LS-Evaluacion): the seven conditions to confirm or correct on the left, and beside them
 * the points chosen so far and what the signature needs. `before` and `after` go above and under the conditions.
 */
export default function AssessmentWorkspace({
    assessment,
    conditions,
    canEdit,
    canSign,
    signError,
    before,
    after,
}: {
    assessment: AssessmentRecord;
    conditions: AssessmentCondition[];
    canEdit: boolean;
    canSign: boolean;
    signError: string | undefined;
    before: ReactNode;
    after: ReactNode;
}) {
    const [scores, setScores] = useState<Partial<Record<string, number>>>(
        assessment.scores,
    );
    const keys = conditions.map((condition) => condition.key);

    return (
        <div className="grid gap-8 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]">
            <div className="flex min-w-0 flex-col gap-8">
                {before}
                <div className="card p-6">
                    <AssessmentForm
                        assessment={assessment}
                        conditions={conditions}
                        editable={canEdit}
                        onScore={(key, points) =>
                            setScores((now) => ({ ...now, [key]: points }))
                        }
                    />
                </div>
                {after}
            </div>
            <aside className="flex flex-col gap-4 self-start">
                <AssessmentScoreCard scores={scores} keys={keys} />
                {canSign && (
                    <AssessmentChecklist
                        assessmentId={assessment.id}
                        answered={scoreSummary(scores, keys).answered}
                        conditions={keys.length}
                        verifiedOn={assessment.verifiedOn}
                        error={signError}
                    />
                )}
            </aside>
        </div>
    );
}
