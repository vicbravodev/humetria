import DeviceItem from '@/components/attendance/device-item';
import EnrollButton from '@/components/attendance/enroll-button';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { deviceStatus } from '@/lib/attendance/devices';
import type { DevicePerson } from '@/types';

/** A person: who, where, their devices (each revocable while active), where they stand, and their link to send. */
export default function DevicePersonRow({
    person,
    canManage,
}: {
    person: DevicePerson;
    canManage: boolean;
}) {
    const status = deviceStatus(person.status);

    return (
        <TableRow>
            <TableCell>
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{person.name}</span>
                        <span className="text-caption text-muted-foreground">
                            {person.jobTitle}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">
                {person.center}
            </TableCell>
            <TableCell className="min-w-72 whitespace-normal">
                {person.devices.length === 0 ? (
                    <span className="text-muted-foreground">
                        Aún no vincula su teléfono
                    </span>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {person.devices.map((device) => (
                            <DeviceItem
                                key={device.id}
                                device={device}
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
                    <EnrollButton person={person} />
                </TableCell>
            )}
        </TableRow>
    );
}
