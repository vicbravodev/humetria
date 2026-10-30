import { cn } from '@/lib/utils';
import type { StepProgress } from '@/lib/survey';

/** "Sección 1 de 8 · 1 de 9 respondidas", with one segment per section: progress by section, not a slow percentage. */
export default function SectionProgress({
    progress,
}: {
    progress: StepProgress;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <span className="font-medium">
                    Sección {progress.section} de {progress.sections}
                </span>
                <span className="text-muted-foreground">
                    {progress.answered} de {progress.inSection} respondidas
                </span>
            </div>
            <div className="flex gap-1" aria-hidden="true">
                {Array.from({ length: progress.sections }, (_, index) => (
                    <span
                        key={index}
                        className={cn(
                            'h-1 flex-1 rounded-full',
                            index < progress.section
                                ? 'bg-primary'
                                : 'bg-muted',
                        )}
                    />
                ))}
            </div>
        </div>
    );
}
