import { usePage } from '@inertiajs/react';
import DashboardFirstStep from '@/components/tenancy/dashboard-first-step';
import DashboardRecommendation from '@/components/tenancy/dashboard-recommendation';
import { nextStep, unansweredCenters } from '@/lib/dashboard';
import { repeatsEvaluationStep } from '@/lib/first-steps/dashboard';
import type { CenterRoutes } from '@/types';

/**
 * The step the panel recommends; while the first steps still list the same evaluation as pending, it points at their
 * button instead of repeating it.
 */
export default function DashboardNextStep(props: {
    centers: CenterRoutes[];
    setsUp: boolean;
}) {
    const { firstSteps, tenant } = usePage().props;
    const modules = tenant?.modules;
    const step = nextStep(props.centers, modules);
    const questions =
        props.setsUp && unansweredCenters(props.centers, modules) > 0;
    const evaluation = firstSteps?.steps.find(
        (each) => each.key === 'evaluation',
    );

    if (
        evaluation !== undefined &&
        repeatsEvaluationStep(
            firstSteps ?? null,
            questions ? 'questions' : (step?.norm ?? null),
        )
    ) {
        return <DashboardFirstStep evaluation={evaluation} />;
    }

    return <DashboardRecommendation {...props} />;
}
