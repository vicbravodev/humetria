import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import AccessKeyItem from '@/components/worker-access/access-key-item';
import InviteButton from '@/components/worker-access/invite-button';
import ReissueButton from '@/components/worker-access/reissue-button';
import {
    accessStatus,
    inviteCaption,
    nextForPerson,
} from '@/lib/worker-access/access';
import type { AccessPerson } from '@/types';

/** A person: who, where, their invitation, their keys (each revocable while active), where they stand, and what RH can do. */
export default function AccessRow({
    person,
    canManage,
}: {
    person: AccessPerson;
    canManage: boolean;
}) {
    const status = accessStatus(person.status);

    return (
        <TableRow>
            <TableCell className="max-w-64 whitespace-normal max-md:w-full max-md:max-w-none">
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{person.name}</span>
                        <span className="text-caption text-muted-foreground">
                            {person.jobTitle} · {person.center}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="min-w-56 text-sm whitespace-normal text-muted-foreground max-md:w-full">
                {inviteCaption(person)}
            </TableCell>
            <TableCell className="min-w-64 whitespace-normal max-md:w-full">
                {person.keys.length === 0 ? (
                    <span className="text-sm text-muted-foreground">
                        {nextForPerson(person)}
                    </span>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {person.keys.map((key) => (
                            <AccessKeyItem
                                key={key.id}
                                accessKey={key}
                                owner={person.name}
                                canManage={canManage}
                            />
                        ))}
                    </ul>
                )}
            </TableCell>
            <TableCell>
                <Badge variant={status.variant}>{status.label}</Badge>
            </TableCell>
            {canManage && (
                <TableCell className="text-right">
                    {person.status === 'with_key' ? (
                        <ReissueButton person={person} />
                    ) : (
                        <InviteButton person={person} />
                    )}
                </TableCell>
            )}
        </TableRow>
    );
}
