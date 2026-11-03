import { Form } from '@inertiajs/react';
import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import AssignPersonForm from '@/components/seating/assign-person-form';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy } from '@/routes/tenant/seating/assignments';
import type { Paginated, PositionPerson } from '@/types';

/** The people who hold the position now (pantalla Empleados): RH adds and removes them; removing ends the assignment. */
export default function PositionPeople({
    positionId,
    people,
    canManage,
}: {
    positionId: string;
    people: Paginated<PositionPerson>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-label="Personas del puesto"
            className="flex flex-col gap-4"
        >
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                Personas en este puesto
            </h2>
            {canManage && <AssignPersonForm positionId={positionId} />}
            {people.total === 0 ? (
                <EmptyState icon={Users} title="Sin personas asignadas">
                    Asigna a quienes ocupan este puesto: cada una tendrá su
                    evaluación de riesgo.
                </EmptyState>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Persona</TableHead>
                            <TableHead>Área</TableHead>
                            <TableHead>Desde</TableHead>
                            <TableHead className="w-px">
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {people.data.map((person) => (
                            <TableRow key={person.assignmentId}>
                                <TableCell className="font-medium">
                                    {person.name}
                                </TableCell>
                                <TableCell>{person.area}</TableCell>
                                <TableCell>{person.startsOn}</TableCell>
                                <TableCell className="text-right">
                                    {canManage && (
                                        <Form
                                            {...destroy.form({
                                                tenant: tenantSlug,
                                                assignment: person.assignmentId,
                                            })}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={processing}
                                                    aria-label={`Quitar a ${person.name} del puesto`}
                                                >
                                                    Quitar
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
            <Pagination
                page={people}
                label="Páginas de personas del puesto"
                pinned={false}
            />
        </section>
    );
}
