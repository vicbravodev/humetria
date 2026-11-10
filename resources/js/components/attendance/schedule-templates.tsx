import { Button } from '@/components/ui/button';
import type { ScheduleTemplate } from '@/lib/attendance/schedule';

/** Where to start: a common week that fills the days, and that RH edits from there. */
export default function ScheduleTemplates({
    templates,
    onPick,
}: {
    templates: ScheduleTemplate[];
    onPick: (template: ScheduleTemplate) => void;
}) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">
                Empieza con una plantilla
            </span>
            <div className="flex flex-wrap gap-2">
                {templates.map((template) => (
                    <Button
                        key={template.id}
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onPick(template)}
                    >
                        {template.label}
                    </Button>
                ))}
            </div>
        </div>
    );
}
