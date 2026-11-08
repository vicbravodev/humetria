import ReportDayRow from '@/components/attendance/report-day-row';
import Initials from '@/components/humetria/initials';
import { TableCell, TableRow } from '@/components/ui/table';
import type { ReportPerson } from '@/types';

/** A person of the report: who, then each of their days in the period. */
export default function ReportPersonRows({ person }: { person: ReportPerson }) {
    return (
        <>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
                <TableCell colSpan={7}>
                    <div className="flex items-center gap-3">
                        <Initials name={person.name} />
                        <div className="flex min-w-0 flex-col">
                            <span className="font-medium">{person.name}</span>
                            <span className="text-caption text-muted-foreground">
                                {person.jobTitle} · {person.days.length}{' '}
                                {person.days.length === 1 ? 'día' : 'días'}
                            </span>
                        </div>
                    </div>
                </TableCell>
            </TableRow>
            {person.days.map((day) => (
                <ReportDayRow key={day.date} day={day} />
            ))}
        </>
    );
}
