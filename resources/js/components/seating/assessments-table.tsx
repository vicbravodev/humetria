import { ClipboardCheck, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import RiskLevel from '@/components/humetria/risk-level';
import AssessmentRowAction from '@/components/seating/assessment-row-action';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AssessmentPerson, Paginated } from '@/types';

/** The people of a center who work standing with their assessment: its state, its level and the next step. */
export default function AssessmentsTable({
    workCenterId,
    people,
    canAssess,
    search,
}: {
    workCenterId: string;
    people: Paginated<AssessmentPerson>;
    canAssess: boolean;
    /** What the list was searched for: an empty result then means nobody matched, not nobody to assess (C7). */
    search: string;
}) {
    if (people.total === 0 && search.trim() !== '') {
        return (
            <EmptyState icon={Users} title="Nadie coincide">
                Prueba con otro nombre.
            </EmptyState>
        );
    }

    if (people.total === 0) {
        return (
            <EmptyState icon={ClipboardCheck} title="Sin personas que evaluar">
                Aquí aparecen las personas asignadas a un puesto de pie del
                centro. Asígnalas desde cada puesto.
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Puesto</TableHead>
                        <TableHead>Evaluación</TableHead>
                        <TableHead>Nivel de riesgo</TableHead>
                        <TableHead className="w-px">
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.data.map((person) => (
                        <TableRow key={person.employeeId}>
                            <TableCell className="font-medium">
                                {person.name}
                            </TableCell>
                            <TableCell>{person.positionName}</TableCell>
                            <TableCell>
                                <Badge
                                    variant={
                                        person.state === 'signed'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {person.stateLabel}
                                </Badge>
                                {person.signedOn !== null && (
                                    <span className="ml-2 text-caption text-muted-foreground">
                                        {person.signedOn}
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {person.level !== null &&
                                person.levelLabel !== null ? (
                                    <RiskLevel
                                        level={person.level}
                                        label={person.levelLabel}
                                    />
                                ) : (
                                    '—'
                                )}
                            </TableCell>
                            <TableCell className="text-right">
                                <AssessmentRowAction
                                    workCenterId={workCenterId}
                                    person={person}
                                    canAssess={canAssess}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={people}
                label="Páginas de personas"
                pinned={false}
            />
        </>
    );
}
