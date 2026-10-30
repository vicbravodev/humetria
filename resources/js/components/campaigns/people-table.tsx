import type { ReactNode } from 'react';
import { useState } from 'react';
import GuideStatusBadge from '@/components/campaigns/guide-status-badge';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { matchesPerson } from '@/lib/campaigns';
import type { CampaignPerson } from '@/types';

/**
 * Who was invited and whether each answered each guide, with a search by name or area. What anyone answered is never
 * here.
 */
export default function PeopleTable({
    people,
    action,
}: {
    people: CampaignPerson[];
    /** What can be done for one person, like sending their link again. */
    action?: (person: CampaignPerson) => ReactNode;
}) {
    const [search, setSearch] = useState('');
    const shown = people.filter((person) => matchesPerson(person, search));

    return (
        <div className="flex flex-col gap-4">
            <Input
                type="search"
                aria-label="Buscar por nombre o área"
                placeholder="Buscar por nombre o área"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="max-w-sm"
            />
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
                    {shown.map((person) => (
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
            {shown.length === 0 && (
                <p className="text-center text-sm text-muted-foreground">
                    Nadie coincide con la búsqueda.
                </p>
            )}
        </div>
    );
}
