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
import type { QualityPerson } from '@/types';

/** The people of a gap of the reporting line, by name; whoever may change the chart fixes each one on its row. */
export default function OrgQualityTable({
    people,
    canManage,
}: {
    people: QualityPerson[];
    canManage: boolean;
}) {
    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Personas por revisar, por nombre
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Área y centro de trabajo</TableHead>
                    <TableHead>Jefe directo</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <TableRow key={person.id}>
                        <TableCell className="whitespace-normal max-md:w-full">
                            <div className="flex items-center gap-3">
                                <Initials name={person.name} />
                                <div className="min-w-36">
                                    <p className="font-medium">{person.name}</p>
                                    <p className="text-caption text-muted-foreground">
                                        {person.job_title}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell className="whitespace-normal text-muted-foreground max-md:w-full">
                            {placeText(person)}
                        </TableCell>
                        <TableCell className="whitespace-normal max-md:w-full">
                            <div className="grid gap-2">
                                {person.manager === null ? (
                                    !canManage && (
                                        <span className="text-muted-foreground">
                                            Sin jefe directo
                                        </span>
                                    )
                                ) : (
                                    <p className="flex flex-wrap items-center gap-2">
                                        {person.manager.label}
                                        <Badge variant="secondary">
                                            Dado de baja
                                        </Badge>
                                    </p>
                                )}
                                {canManage && <OrgQualityFix person={person} />}
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
