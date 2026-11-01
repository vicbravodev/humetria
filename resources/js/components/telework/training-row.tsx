import { Download, GraduationCap } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { attendeesText } from '@/lib/telework/training';
import { evidence } from '@/routes/tenant/telework/trainings';
import type { TrainingRow as Row } from '@/types';

/** One training given: when and where, how, the 8.1 topics it covered and how many attended. */
export default function TrainingRow({
    tenantSlug,
    row,
}: {
    tenantSlug: string;
    row: Row;
}) {
    return (
        <li className="flex flex-col gap-3 rounded-xl border bg-card px-[22px] py-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3.5">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                        <GraduationCap aria-hidden className="size-5" />
                    </span>
                    <div className="flex min-w-0 flex-col">
                        <p className="font-medium">
                            {describeDate(row.heldOn) ?? row.heldOn}
                        </p>
                        <p className="text-[13px] leading-[18px] text-muted-foreground">
                            {row.workCenter.name} · {row.modality} ·{' '}
                            {attendeesText(row.attendees)}
                        </p>
                    </div>
                </div>
                {row.hasEvidence && (
                    <Button variant="outline" asChild>
                        <a
                            href={evidence.url({
                                tenant: tenantSlug,
                                training: row.id,
                            })}
                        >
                            <Download aria-hidden />
                            Lista de asistencia
                        </a>
                    </Button>
                )}
            </div>
            <ul aria-label="Temas" className="flex flex-wrap gap-2">
                {row.topics.map((topic) => (
                    <li key={topic}>
                        <Badge variant="secondary">{topic}</Badge>
                    </li>
                ))}
            </ul>
        </li>
    );
}
