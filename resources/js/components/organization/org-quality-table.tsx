import Initials from '@/components/humetria/initials';
import StackedTable from '@/components/humetria/stacked-table';
import OrgQualityFix from '@/components/organization/org-quality-fix';
import { Badge } from '@/components/ui/badge';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { placeText } from '@/lib/org-chart/labels';
import { cn } from '@/lib/utils';
import type { QualityPerson } from '@/types';

/**
 * The people of the list, by name: the row of whoever needs a manager is tinted sand, and whoever may change the chart
 * chooses each manager on its row. The size of each team is on the right, in tabular figures.
 */
export default function OrgQualityTable({
    people,
    canManage,
}: {
    people: QualityPerson[];
    canManage: boolean;
}) {
    return (
        <div className="overflow-hidden rounded-xl border bg-card">
            <StackedTable>
                <TableCaption className="sr-only">
                    Personas del organigrama, con su jefe directo
                </TableCaption>
                <TableHeader className="bg-muted">
                    <TableRow>
                        <TableHead className="text-caption font-medium tracking-wide uppercase">
                            Persona
                        </TableHead>
                        <TableHead className="text-caption font-medium tracking-wide uppercase">
                            Jefe directo
                        </TableHead>
                        <TableHead className="text-caption font-medium tracking-wide uppercase">
                            Centro · Área
                        </TableHead>
                        <TableHead className="text-right text-caption font-medium tracking-wide uppercase">
                            Directos
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.map((person) => (
                        <TableRow
                            key={person.id}
                            className={cn(
                                (person.manager === null ||
                                    person.manager_inactive) &&
                                    'bg-warning text-warning-foreground hover:bg-warning',
                            )}
                        >
                            <TableCell className="whitespace-normal max-md:w-full">
                                <div className="flex items-center gap-3">
                                    <Initials name={person.name} />
                                    <div className="min-w-36">
                                        <p className="font-medium">
                                            {person.name}
                                        </p>
                                        <p className="text-caption">
                                            {person.job_title}
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell className="min-h-14 whitespace-normal max-md:w-full">
                                <div className="grid gap-2 py-2">
                                    {person.manager_inactive && (
                                        <Badge
                                            variant="secondary"
                                            className="w-fit"
                                        >
                                            Su jefe fue dado de baja
                                        </Badge>
                                    )}
                                    {canManage ? (
                                        <OrgQualityFix person={person} />
                                    ) : (
                                        <span>
                                            {person.manager?.label ??
                                                'Sin jefe directo'}
                                        </span>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className="whitespace-normal max-md:w-full">
                                {[person.work_center, person.area]
                                    .filter((part) => part !== '')
                                    .join(' · ') || placeText(person)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums max-md:text-left">
                                <span className="md:hidden">Directos: </span>
                                {person.direct_reports}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
        </div>
    );
}
