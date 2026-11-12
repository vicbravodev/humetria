import type { NormKey } from '@/lib/compliance-routes';
import type { FirstSteps } from '@/types/first-steps';

/** True when the dashboard's recommended-step card would repeat the evaluation step of the first steps card. */
export function repeatsEvaluationStep(
    firstSteps: FirstSteps | null,
    recommendation: 'questions' | NormKey | null,
): boolean {
    if (firstSteps === null || firstSteps.hidden || recommendation === null) {
        return false;
    }

    const step = firstSteps.steps.find(
        (candidate) => candidate.key === 'evaluation',
    );

    if (step === undefined || step.done) {
        return false;
    }

    if (recommendation === 'questions') {
        return step.tool === 'nom037' || step.tool === 'seating';
    }

    return recommendation === step.tool;
}
