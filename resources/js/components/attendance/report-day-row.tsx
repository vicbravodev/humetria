import { TriangleAlert } from 'lucide-react';
import ReportEvidence from '@/components/attendance/report-evidence';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { confirmationBadge, dayHours, endText } from '@/lib/attendance/report';
import { dayTitle, duration } from '@/lib/attendance/week-text';
import type { ReportDay } from '@/types';

/** One day of a person: its local start and end, breaks, hours as the law pays them, evidence, corrections and answer. */
export default function ReportDayRow({ day }: { day: ReportDay }) {
    const badge = confirmationBadge(day.confirmation);
    const notes = [...day.corrections, ...day.flags];

    return (
        <TableRow>
            <TableCell className="pl-8 font-medium whitespace-nowrap max-md:w-full">
                {dayTitle(day.date)}
            </TableCell>
            <TableCell className="whitespace-nowrap tabular-nums">
                {day.start ?? '—'} – {endText(day)}
            </TableCell>
            <TableCell className="tabular-nums">
                <span className="text-muted-foreground md:hidden">Pausas </span>
                {day.breakMinutes === 0 ? '—' : duration(day.breakMinutes)}
            </TableCell>
            <TableCell className="tabular-nums">{dayHours(day)}</TableCell>
            <TableCell className="whitespace-nowrap">
                <ReportEvidence level={day.evidence} />
            </TableCell>
            <TableCell className="min-w-56 whitespace-normal max-md:w-full">
                {notes.length === 0 ? (
                    <span className="text-muted-foreground">Sin notas</span>
                ) : (
                    <ul className="flex flex-col gap-1 text-sm">
                        {day.corrections.map((correction) => (
                            <li key={correction}>{correction}</li>
                        ))}
                        {day.flags.map((flag) => (
                            <li
                                key={flag}
                                className="flex items-start gap-1.5 text-destructive"
                            >
                                <TriangleAlert
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                {flag}
                            </li>
                        ))}
                    </ul>
                )}
            </TableCell>
            <TableCell>
                <Badge variant={badge.variant}>{badge.label}</Badge>
            </TableCell>
        </TableRow>
    );
}
