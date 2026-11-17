import StepBar from '@/components/landing/step-bar';

const GOALS = [
    { label: 'Reducir la rotación', value: 0.72 },
    { label: 'Capacitación anual completa', value: 0.45 },
    { label: 'Satisfacción del equipo', value: 0.88 },
];

/** A made-up list of goals with their progress, to show what the OKRs of a team will look like. */
export default function RoadmapGoalsSketch() {
    return (
        <div aria-hidden className="grid gap-3.5 rounded-lg bg-muted/60 p-4">
            {GOALS.map((goal, index) => (
                <div key={goal.label} className="grid gap-1.5">
                    <div className="flex items-baseline justify-between gap-3 text-meta">
                        <span className="font-medium text-foreground">
                            {goal.label}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {Math.round(goal.value * 100)} %
                        </span>
                    </div>
                    <StepBar value={goal.value} delay={0.2 + index * 0.12} />
                </div>
            ))}
        </div>
    );
}
