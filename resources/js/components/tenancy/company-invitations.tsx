import Pagination from '@/components/humetria/pagination';
import type { CompanyInvitationRow, Paginated } from '@/types';

/** The invitations of the company still waiting for an answer, the owner's and its members'. */
export default function CompanyInvitations({
    invitations,
}: {
    invitations: Paginated<CompanyInvitationRow>;
}) {
    if (invitations.total === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No hay invitaciones abiertas.
            </p>
        );
    }

    return (
        <div className="grid gap-3">
            <ul className="divide-y card">
                {invitations.data.map((invitation) => (
                    <li
                        key={invitation.id}
                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3"
                    >
                        <span className="flex flex-col">
                            {invitation.name}
                            <span className="text-caption break-words text-muted-foreground">
                                {invitation.email}
                            </span>
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {invitation.status_label} · vence el{' '}
                            {invitation.expires_on}
                        </span>
                    </li>
                ))}
            </ul>
            <Pagination
                page={invitations}
                label="Páginas de invitaciones"
                pinned={false}
            />
        </div>
    );
}
