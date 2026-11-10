import SetupStepCard from '@/components/attendance/setup-step-card';
import { setupSteps } from '@/lib/attendance/setup';
import type { AttendanceSetup } from '@/types';

/** The four steps to the first mark, side by side: the first one still open is the one to do now. */
export default function SetupSteps({ setup }: { setup: AttendanceSetup }) {
    const steps = setupSteps(setup);
    const current = steps.find((step) => !step.complete)?.key;

    return (
        <div className="grid gap-4 min-[1100px]:grid-cols-4 sm:grid-cols-2">
            {steps.map((step) => (
                <SetupStepCard
                    key={step.key}
                    step={step}
                    current={step.key === current}
                />
            ))}
        </div>
    );
}
