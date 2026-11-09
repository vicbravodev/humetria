import { useState } from 'react';
import DayCorrection from '@/components/attendance/day-correction';
import WeekMark from '@/components/attendance/week-mark';
import EvidenceDots from '@/components/attendance/evidence-dots';
import { Badge } from '@/components/ui/badge';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import type { WeekDay as Day, WeekMark as Mark } from '@/lib/attendance/week';
import { dayEvidence, dayTitle, duration } from '@/lib/attendance/week-text';

/**
 * One day of the week: its marks with their local time, what it worked against what was scheduled, its flags, and the
 * ways to ask RH for a correction (a mark that is wrong, a mark missing).
 */
export default function WeekDay({
    day,
    pending,
    working,
    onCorrect,
}: {
    day: Day;
    /** The marks with a request waiting for RH: none more of them. */
    pending: string[];
    working: boolean;
    onCorrect: (draft: CorrectionDraft) => Promise<boolean>;
}) {
    const [asking, setAsking] = useState<Mark | 'missing' | null>(null);
    const evidence = dayEvidence(day.marks);
    const scheduled = day.restDay
        ? 'Descanso'
        : `de ${duration(day.scheduledMinutes)}`;

    return (
        <li className="flex flex-col gap-2 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-base font-semibold">
                    {dayTitle(day.date)}
                </h3>
                <p className="text-base tabular-nums">
                    <span className="font-semibold">
                        {duration(day.workedMinutes)}
                    </span>{' '}
                    <span className="text-muted-foreground">{scheduled}</span>
                </p>
            </div>
            {evidence !== null && (
                <p className="flex items-center gap-2 text-base text-muted-foreground">
                    <EvidenceDots dots={evidence.dots} />
                    <span className="sr-only">Evidencia </span>
                    {evidence.text}
                </p>
            )}
            {day.marks.length === 0 ? (
                <p className="text-base text-muted-foreground">Sin marcas.</p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {day.marks.map((mark) => (
                        <WeekMark
                            key={mark.id}
                            mark={mark}
                            asked={pending.includes(mark.id)}
                            onAsk={() => setAsking(mark)}
                        />
                    ))}
                </ul>
            )}
            {day.missingEnd && (
                <Badge variant="warning">Sin salida marcada</Badge>
            )}
            <DayCorrection
                date={day.date}
                asking={asking}
                working={working}
                onMissing={() => setAsking('missing')}
                onClose={() => setAsking(null)}
                onCorrect={onCorrect}
            />
        </li>
    );
}
