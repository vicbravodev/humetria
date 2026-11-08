import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { enroll } from '@/routes/tenant/attendance/devices';
import type { DevicePerson } from '@/types';

/**
 * Sends a person their link to link a phone: to their email, which RH never sees; for a person without one, RH gets it
 * once to hand over. A new link ends the one sent before.
 */
export default function EnrollButton({ person }: { person: DevicePerson }) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline" size="sm">
                    {person.hasEmail
                        ? 'Enviar enlace de alta'
                        : 'Crear enlace de alta'}
                </Button>
            }
            title={`¿Enviar el enlace de alta a ${person.name}?`}
            description={
                person.hasEmail
                    ? 'Le llega a su correo: lo abre en su teléfono y crea su llave de acceso. Funciona una vez y vence en 48 h; si había otro pendiente, deja de funcionar.'
                    : 'No tiene correo: verás el enlace una sola vez para entregárselo. Funciona una vez y vence en 48 h; si había otro pendiente, deja de funcionar.'
            }
            confirmLabel={person.hasEmail ? 'Enviar enlace' : 'Crear enlace'}
            form={enroll.form(tenantSlug)}
            transform={() => ({ employee_id: person.id })}
            preserveScroll
            destructive={false}
        />
    );
}
