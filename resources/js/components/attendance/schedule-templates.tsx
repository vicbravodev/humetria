import { templateSummary } from '@/lib/attendance/schedule-templates';
import type { ScheduleTemplate } from '@/lib/attendance/schedule-templates';
import { cn } from '@/lib/utils';

/** Where to start: the common weeks as cards that say what they fill and its total; the one the week is stays marked. */
export default function ScheduleTemplates({
    templates,
    chosen,
    onPick,
}: {
    templates: ScheduleTemplate[];
    chosen: string | null;
    onPick: (template: ScheduleTemplate) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Su semana de trabajo
            </legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {templates.map((template) => (
                    <button
                        key={template.id}
                        type="button"
                        aria-pressed={template.id === chosen}
                        onClick={() => onPick(template)}
                        className={cn(
                            'flex min-h-11 flex-col items-start gap-0.5 rounded-lg border border-input bg-card px-4 py-2.5 text-left transition-colors duration-100 ease-out hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            template.id === chosen &&
                                'border-2 border-primary bg-accent px-[15px] py-[9px]',
                        )}
                    >
                        <span className="font-medium">{template.title}</span>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {templateSummary(template)}
                        </span>
                    </button>
                ))}
            </div>
        </fieldset>
    );
}
