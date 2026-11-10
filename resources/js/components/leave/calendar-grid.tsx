import CalendarHead from '@/components/leave/calendar-head';
import CalendarRow from '@/components/leave/calendar-row';
import { daysOfMonth } from '@/lib/leave/calendar';
import type { LeaveCalendarPerson } from '@/types';

const KEYS = [
    { label: 'Vacaciones', tone: 'bg-primary' },
    { label: 'Permiso', tone: 'bg-muted-foreground' },
];

/**
 * The calendar of the month: a row per person, a column per day. On a phone the grid scrolls inside its own box, so the
 * page never does.
 */
export default function CalendarGrid({
    month,
    today,
    out,
    holidays,
    people,
    canManage,
}: {
    month: string;
    today: string;
    out: number[];
    holidays: string[];
    people: LeaveCalendarPerson[];
    canManage: boolean;
}) {
    const days = daysOfMonth(month);

    return (
        <div className="flex flex-col gap-3">
            <div
                role="region"
                aria-label="Calendario del equipo"
                tabIndex={0}
                className="overflow-x-auto card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <div style={{ minWidth: 176 + days.length * 32 }}>
                    <CalendarHead days={days} today={today} out={out} />
                    <ul>
                        {people.map((person) => (
                            <CalendarRow
                                key={person.id}
                                person={person}
                                days={days}
                                holidays={holidays}
                            />
                        ))}
                    </ul>
                </div>
            </div>
            <p className="flex flex-wrap items-center gap-x-5 gap-y-1 px-1 text-sm text-muted-foreground">
                {KEYS.map(({ label, tone }) => (
                    <span key={label} className="flex items-center gap-1.5">
                        <i
                            aria-hidden
                            className={`size-2.5 rounded-[3px] ${tone}`}
                        />
                        {label}
                    </span>
                ))}
                {canManage && (
                    <span className="flex items-center gap-1.5">
                        <i
                            aria-hidden
                            className="size-2.5 rounded-[3px] border border-dashed border-muted-foreground"
                        />
                        Por responder
                    </span>
                )}
                <span>
                    Las columnas sombreadas son fines de semana y festivos.
                </span>
            </p>
        </div>
    );
}
