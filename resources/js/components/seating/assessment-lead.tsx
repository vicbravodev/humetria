import { Info } from 'lucide-react';
import AssessmentResult from '@/components/seating/assessment-result';
import type { AssessmentRecord, MeasureSuggestions } from '@/types';

/** Over the conditions: the result once signed; in a draft (board LS-Evaluacion), where its conditions come from. */
export default function AssessmentLead({
    assessment,
    suggestions,
}: {
    assessment: AssessmentRecord;
    suggestions: MeasureSuggestions | null;
}) {
    if (assessment.status === 'signed') {
        return (
            <AssessmentResult
                assessment={assessment}
                suggestions={suggestions}
            />
        );
    }

    return (
        <p className="flex items-start gap-3 rounded-xl bg-muted px-5 py-3.5 text-meta">
            <Info aria-hidden className="mt-0.5 size-5 shrink-0 text-primary" />
            Cinco condicionantes vienen del puesto y dos de lo que contestó la
            persona. Confírmalas o corrígelas: el cambio queda registrado.
        </p>
    );
}
