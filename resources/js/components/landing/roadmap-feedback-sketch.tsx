import StepBar from '@/components/landing/step-bar';

const SKILLS = [
    { label: 'Comunicación', score: 4.3 },
    { label: 'Liderazgo', score: 3.8 },
    { label: 'Trabajo en equipo', score: 4.6 },
];

/** A made-up result of a 360 evaluation, scores out of 5, to show what a person's feedback will look like. */
export default function RoadmapFeedbackSketch() {
    return (
        <div aria-hidden className="grid gap-3.5 rounded-lg bg-muted/60 p-4">
            {SKILLS.map((skill, index) => (
                <div key={skill.label} className="grid gap-1.5">
                    <div className="flex items-baseline justify-between gap-3 text-meta">
                        <span className="font-medium text-foreground">
                            {skill.label}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {skill.score.toFixed(1)} de 5
                        </span>
                    </div>
                    <StepBar
                        value={skill.score / 5}
                        delay={0.2 + index * 0.12}
                    />
                </div>
            ))}
            <p className="text-meta text-muted-foreground">
                Promedio de su jefe, sus pares y su autoevaluación.
            </p>
        </div>
    );
}
