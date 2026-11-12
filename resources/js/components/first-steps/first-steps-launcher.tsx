import { PopoverTrigger } from '@/components/ui/popover';
import { progress } from '@/lib/first-steps/steps';
import type { FirstStep } from '@/types/first-steps';

const RADIUS = 11;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * The pill beside Humi that opens the first steps: a ring that fills as steps get done and "2 de 5". With Humi docked at the
 * right it moves left of the dock instead of covering it.
 */
export default function FirstStepsLauncher({ steps }: { steps: FirstStep[] }) {
    const { done, total, label } = progress(steps);
    const filled = total === 0 ? 0 : (done / total) * CIRCUMFERENCE;

    return (
        <PopoverTrigger asChild>
            <button
                type="button"
                aria-label={`Primeros pasos: ${label} pasos`}
                className="fixed right-[4.75rem] bottom-24 z-40 flex h-12 items-center gap-2 rounded-full border bg-card pr-4 pl-3 text-sm font-medium shadow-float transition-transform duration-150 ease-out [view-transition-name:first-steps] group-has-[aside[aria-label=Humi]]/sidebar-wrapper:right-[436px] hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none md:bottom-4 print:hidden"
            >
                <svg
                    aria-hidden
                    viewBox="0 0 28 28"
                    className="size-7 -rotate-90"
                    fill="none"
                    strokeWidth={3}
                >
                    <circle
                        cx={14}
                        cy={14}
                        r={RADIUS}
                        className="stroke-border"
                    />
                    <circle
                        cx={14}
                        cy={14}
                        r={RADIUS}
                        className="stroke-primary"
                        strokeLinecap="round"
                        strokeDasharray={`${filled} ${CIRCUMFERENCE}`}
                    />
                </svg>
                <span aria-hidden>{label}</span>
            </button>
        </PopoverTrigger>
    );
}
