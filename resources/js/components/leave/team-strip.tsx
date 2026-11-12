import { stripDay } from '@/lib/leave/overview';
import { teamLine } from '@/lib/leave/queue';
import { cn } from '@/lib/utils';
import type { LeaveTeam } from '@/types';

/**
 * «Su equipo esos días»: a cell per day of the request with how many of the people of the same manager are away, and
 * under it the sentence with their names. Only that they are away, never why.
 */
export default function TeamStrip({ team }: { team: LeaveTeam }) {
    return (
        <section
            aria-label="Su equipo esos días"
            className="flex flex-col gap-2"
        >
            <h3 className="text-sm font-semibold">Su equipo esos días</h3>
            <ol className="flex flex-wrap gap-1.5">
                {team.days.map((day) => {
                    const { weekday, number } = stripDay(day.date);

                    return (
                        <li
                            key={day.date}
                            aria-label={`${weekday} ${number}: ${day.away} fuera`}
                            className={cn(
                                'flex min-w-11 flex-col items-center rounded-lg border py-1.5 text-center',
                                day.away > 0 && 'bg-accent',
                            )}
                        >
                            <span className="text-caption text-muted-foreground">
                                {weekday} {number}
                            </span>
                            <span
                                className={cn(
                                    'text-sm tabular-nums',
                                    day.away > 0
                                        ? 'font-medium'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {day.away}
                            </span>
                        </li>
                    );
                })}
            </ol>
            <p className="text-meta text-muted-foreground">{teamLine(team)}</p>
        </section>
    );
}
