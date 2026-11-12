import NextStepCard from '@/components/humetria/next-step-card';
import { Button } from '@/components/ui/button';
import { openFirstSteps } from '@/lib/first-steps/open';
import { stepCopy } from '@/lib/first-steps/steps';
import type { FirstStep } from '@/types/first-steps';

/** The recommended-step card when it would repeat the evaluation step: it points at the Primeros pasos button instead. */
export default function DashboardFirstStep({
    evaluation,
}: {
    evaluation: FirstStep;
}) {
    return (
        <NextStepCard
            title={stepCopy(evaluation).title}
            meta="En tus primeros pasos"
            action={<Button onClick={openFirstSteps}>Ver mis pasos</Button>}
        >
            Es tu siguiente paso. Síguelo desde el botón Primeros pasos.
        </NextStepCard>
    );
}
