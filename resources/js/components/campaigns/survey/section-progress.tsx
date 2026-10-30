import { cn } from '@/lib/utils';
import type { StepProgress } from '@/lib/survey';

/** "Guía I · Sección 1 de 4 · 1 de 6 respondidas", with one segment per section: progress by section, not a slow percentage. */
export default function SectionProgress({
    guide,
    progress,
}: {
    guide: string;
    progress: StepProgress;
}) {
    return (
        <div className="flex flex-col gap-2 md:hidden">
            <div className="flex items-baseline justify-between gap-4 text-base">
                <span className="font-medium">
                    {guide} · Sección {progress.section} de {progress.sections}
                </span>
                <span className="shrink-0 text-muted-foreground">
                    {progress.answered} de {progress.inSection} respondidas
                </span>
            </div>
            <div className="flex gap-1" aria-hidden="true">
                {Array.from({ length: progress.sections }, (_, index) => (
                    <span
                        key={index}
                        className={cn(
                            'h-1.5 flex-1 rounded-full',
                            index < progress.section
                                ? 'bg-primary'
                                : 'bg-track',
                        )}
                    />
                ))}
            </div>
        </div>
    );
}
