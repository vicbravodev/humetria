import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { AckCommitteeMember } from '@/types';

/** The Comisión members who also confirm the risk information; they do it themselves, in this panel. */
export default function AckCommittee({
    members,
}: {
    members: AckCommitteeMember[];
}) {
    if (members.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="ack-committee" className="grid gap-3">
            <h2 id="ack-committee" className="text-xl font-semibold">
                Comisión de seguridad e higiene
            </h2>
            <ul className="flex flex-col divide-y rounded-lg border bg-card">
                {members.map((member) => (
                    <li
                        key={member.name}
                        className="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span className="font-medium">{member.name}</span>
                        <Badge
                            variant={
                                member.status === 'acknowledged'
                                    ? 'success'
                                    : 'warning'
                            }
                        >
                            {member.status === 'acknowledged'
                                ? `Confirmó · ${describeDate(member.acknowledgedOn ?? '') ?? ''}`
                                : 'Pendiente'}
                        </Badge>
                    </li>
                ))}
            </ul>
        </section>
    );
}
