import { Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { destroy } from '@/routes/tenant/roles';

/**
 * «Borrar rol», after asking. A role someone holds, or that an invitation not yet accepted names, stays: the dialog says so
 * before trying (the server refuses it anyway, and says the same).
 */
export default function DeleteRoleDialog({
    role,
}: {
    role: { id: number; label: string; people: number; invitations: number };
}) {
    const tenantSlug = useTenantSlug();
    const held = role.people + role.invitations > 0;
    const holders = [
        ...(role.people > 0
            ? [countOf(role.people, 'persona', 'personas')]
            : []),
        ...(role.invitations > 0
            ? [
                  countOf(
                      role.invitations,
                      'invitación sin aceptar',
                      'invitaciones sin aceptar',
                  ),
              ]
            : []),
    ].join(' y ');

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline" className="text-destructive">
                    <Trash2 aria-hidden />
                    Borrar rol
                </Button>
            }
            title={`¿Borrar el rol ${role.label}?`}
            description={
                held
                    ? `No puedes borrarlo: tiene ${holders}. Pásalas a otro rol o cancela esas invitaciones primero.`
                    : 'Nadie lo tiene. Ya no aparecerá al invitar ni al cambiar el rol de alguien.'
            }
            confirmLabel="Borrar rol"
            disabled={held}
            form={destroy.form({ tenant: tenantSlug, role: role.id })}
        />
    );
}
