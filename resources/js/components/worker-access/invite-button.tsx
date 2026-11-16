import { usePage } from '@inertiajs/react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { invite } from '@/routes/tenant/worker-access';
import type { AccessPerson, WorkerAccessPageProps } from '@/types';

/**
 * Invites a person to create their key: to their email, which RH never sees; for a person without one, RH gets the link
 * once to hand over. A new invitation ends the one sent before.
 */
export default function InviteButton({ person }: { person: AccessPerson }) {
    const tenantSlug = useTenantSlug();
    const { linkHours } = usePage<WorkerAccessPageProps>().props;

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline" size="sm">
                    {person.hasEmail ? 'Invitar' : 'Crear enlace'}
                </Button>
            }
            title={`¿Invitar a ${person.name}?`}
            description={
                person.hasEmail
                    ? `Le llega a su correo: lo abre en su teléfono y crea su llave de acceso. Funciona una vez y vence en ${linkHours} h; si había otro pendiente, deja de funcionar.`
                    : `No tiene correo: verás el enlace una sola vez para entregárselo. Funciona una vez y vence en ${linkHours} h; si había otro pendiente, deja de funcionar.`
            }
            confirmLabel={
                person.hasEmail ? 'Enviar invitación' : 'Crear enlace'
            }
            form={invite.form(tenantSlug)}
            transform={() => ({ employee_id: person.id })}
            preserveScroll
            destructive={false}
        />
    );
}
