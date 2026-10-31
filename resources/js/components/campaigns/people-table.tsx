import { Mail, MailX } from 'lucide-react';
import type { ReactNode } from 'react';
import GuideStatusBadge from '@/components/campaigns/guide-status-badge';
import Initials from '@/components/humetria/initials';
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
 * Who was invited, how their link reaches them and whether each answered each guide: one page of them. What anyone
 * answered is never here.
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
        <Table>
            <TableCaption className="sr-only">
                Personas invitadas, por nombre
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>Canal</TableHead>
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
                        <TableCell>
                            <span className="flex items-center gap-2.5 font-medium">
                                <Initials name={person.name} />
                                {person.name}
                            </span>
                        </TableCell>
                        <TableCell>{person.area}</TableCell>
                        <TableCell>
                            <span className="flex items-center gap-1.5 text-sm">
                                {person.reachable ? (
                                    <Mail
                                        aria-hidden="true"
                                        className="size-4 text-muted-foreground"
                                    />
                                ) : (
                                    <MailX
                                        aria-hidden="true"
                                        className="size-4 text-muted-foreground"
                                    />
                                )}
                                {person.reachable ? 'Correo' : 'Sin correo'}
                            </span>
                        </TableCell>
                        <TableCell>
                            <div className="flex flex-wrap gap-1.5">
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
                {people.length === 0 && (
                    <TableRow>
                        <TableCell
                            colSpan={action === undefined ? 4 : 5}
                            className="py-8 text-center text-sm text-muted-foreground"
                        >
                            Nadie coincide con los filtros.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
