import { Link, usePage } from '@inertiajs/react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { centerState, notReadyNote } from '@/lib/attendance/center-state';
import { center as show } from '@/routes/tenant/attendance';
import { enroll } from '@/routes/tenant/attendance/devices';
import type { AttendanceDevicesProps, DevicePerson } from '@/types';

/**
 * Sends a person their link to link a phone: to their email, which RH never sees; for a person without one, RH gets it
 * once to hand over. A new link ends the one sent before.
 */
export default function EnrollButton({ person }: { person: DevicePerson }) {
    const tenantSlug = useTenantSlug();
    const { linkHours, center } = usePage<AttendanceDevicesProps>().props;
    const notReady =
        center !== null && !centerState(center, center.requiresQr).ready;

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
                    ? `Le llega a su correo: lo abre en su teléfono y crea su llave de acceso. Funciona una vez y vence en ${linkHours} h; si había otro pendiente, deja de funcionar.`
                    : `No tiene correo: verás el enlace una sola vez para entregárselo. Funciona una vez y vence en ${linkHours} h; si había otro pendiente, deja de funcionar.`
            }
            confirmLabel={person.hasEmail ? 'Enviar enlace' : 'Crear enlace'}
            form={enroll.form(tenantSlug)}
            transform={() => ({ employee_id: person.id })}
            preserveScroll
            destructive={false}
        >
            {center !== null && notReady && (
                <Notice
                    action={
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={show({
                                    tenant: tenantSlug,
                                    workCenter: center.id,
                                })}
                            >
                                Configurar el centro
                            </Link>
                        </Button>
                    }
                >
                    {notReadyNote(center.name)}
                </Notice>
            )}
        </ConfirmDialog>
    );
}
