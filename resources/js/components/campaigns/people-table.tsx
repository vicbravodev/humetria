import type { ReactNode } from 'react';
import GuideStatusBadge from '@/components/campaigns/guide-status-badge';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CampaignPerson } from '@/types';

/**
 * Who was invited and whether each answered each guide: one page of them. What anyone answered is never here.
 */
export default function PeopleTable({
    people,
    action,
}: {
    people: CampaignPerson[];
    /** What can be done for one person, like sending their link again. */
    action?: (person: CampaignPerson) => ReactNode;
}) {
    return (
        <div className="flex flex-col gap-4">
            <Table>
                <TableCaption className="sr-only">
                    Personas invitadas, por nombre
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nombre</TableHead>
                        <TableHead>Área</TableHead>
                        <TableHead>Guías</TableHead>
                        {action !== undefined && (
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.map((person) => (
                        <TableRow key={person.id}>
                            <TableCell className="whitespace-normal">
                                <p className="font-medium">{person.name}</p>
                                {!person.reachable && (
                                    <p className="text-sm text-muted-foreground">
                                        Sin correo
                                    </p>
                                )}
                            </TableCell>
                            <TableCell className="text-sm">
                                {person.area}
                            </TableCell>
                            <TableCell>
                                <div className="flex flex-wrap gap-2">
                                    {person.guides.map((guide) => (
                                        <GuideStatusBadge
                                            key={guide.key}
                                            guide={guide}
                                        />
                                    ))}
                                </div>
                            </TableCell>
                            {action !== undefined && (
                                <TableCell className="w-px text-right">
                                    {action(person)}
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            {people.length === 0 && (
                <p className="text-center text-sm text-muted-foreground">
                    Nadie coincide con los filtros.
                </p>
            )}
        </div>
    );
}
