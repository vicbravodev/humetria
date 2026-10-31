import { Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { cancel, resend } from '@/routes/tenant/members/invitations';
import type { MemberInvitationRow as Invitation } from '@/types';

/** An invitation not accepted yet, under the people with access: resent or cancelled from its row. */
export default function MemberInvitationRow({
    tenantSlug,
    invitation,
}: {
    tenantSlug: string;
    invitation: Invitation;
}) {
    const ids = { tenant: tenantSlug, invitation: invitation.id };
    const expired = invitation.status === 'expired';

    return (
        <TableRow>
            <TableCell className="whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <span className="inline-flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px] border-dashed border-input text-muted-foreground">
                        <Mail aria-hidden className="size-3.5" />
                    </span>
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{invitation.name}</span>
                        <span className="text-[13px] leading-[18px] text-muted-foreground">
                            {invitation.email} · {invitation.status_label} ·{' '}
                            {expired ? 'venció' : 'vence'} el{' '}
                            {invitation.expires_on}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <Badge variant="secondary" className="before:hidden">
                    {invitation.role_label}
                </Badge>
            </TableCell>
            <TableCell
                className={
                    expired
                        ? 'text-sm text-destructive'
                        : 'text-sm text-muted-foreground'
                }
            >
                {expired ? 'Vencida' : 'Pendiente'}
            </TableCell>
            <TableCell className="w-px">
                <div className="-my-2 flex justify-end gap-1">
                    <Button asChild size="sm" variant="ghost">
                        <Link
                            href={resend(ids)}
                            as="button"
                            aria-label={`Reenviar la invitación de ${invitation.name}`}
                        >
                            Reenviar
                        </Link>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        variant="ghost"
                        className="text-muted-foreground"
                    >
                        <Link
                            href={cancel(ids)}
                            as="button"
                            aria-label={`Cancelar la invitación de ${invitation.name}`}
                        >
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </TableCell>
        </TableRow>
    );
}
