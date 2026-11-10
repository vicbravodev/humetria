import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { absentNote, weekNotes } from '@/lib/attendance/overview';
import type { OverviewPerson } from '@/types';

/**
 * What warns RH of a person's week: no agreed schedule (with the way to fix it), the limits broken or the week near the
 * cap, and the days away with approved vacations or a leave, which are not days without marks.
 */
export default function PersonWeekNotes({
    person,
    scheduleHref,
}: {
    person: OverviewPerson;
    scheduleHref: string;
}) {
    const notes = weekNotes(person);
    const away = absentNote(person.absentDays);

    return (
        <div className="flex flex-wrap gap-1.5">
            {!person.hasSchedule && (
                <Badge
                    asChild
                    variant="warning"
                    className="relative after:absolute after:inset-x-0 after:-inset-y-2.5 after:content-['']"
                >
                    <Link
                        href={scheduleHref}
                        title="Sin horario acordado se cuenta con 8 h al día y sin día de descanso: fija el suyo."
                    >
                        Sin horario
                    </Link>
                </Badge>
            )}
            {notes.map((note) => (
                <Badge
                    key={note}
                    variant={
                        person.flags.length > 0 ? 'destructive' : 'warning'
                    }
                >
                    {note}
                </Badge>
            ))}
            {away !== null && (
                <Badge
                    variant="secondary"
                    title="Vacaciones o permiso aprobado: no son días sin marcas."
                >
                    {away}
                </Badge>
            )}
            {person.hasSchedule && notes.length === 0 && away === null && (
                <span className="text-muted-foreground">Sin avisos</span>
            )}
        </div>
    );
}
