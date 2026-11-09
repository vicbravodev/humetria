import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { enrollAll } from '@/routes/tenant/attendance/devices';

/** Sends their link to every person of the center with no device and no link that still opens; who has no email is left for RH, one by one. */
export default function EnrollAllButton({
    center,
    waiting,
    linkHours,
}: {
    center: { id: string; name: string };
    /** People of the center with no active device. */
    waiting: number;
    linkHours: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button
                    disabled={waiting === 0}
                    className="h-auto min-h-11 w-full py-2 whitespace-normal sm:w-auto"
                >
                    <Send aria-hidden />
                    Enviar enlaces a todos los que no tienen dispositivo
                </Button>
            }
            title={`¿Enviar su enlace de alta a ${waiting === 1 ? 'la persona' : `las ${waiting} personas`} de ${center.name} sin dispositivo?`}
            description={`Cada quien lo recibe en su correo y lo abre en su teléfono; funciona una vez y vence en ${linkHours} h. A quien ya tenga un enlace vigente no se le envía otro (reenvíaselo desde su renglón) y a quien no tenga correo tampoco: crea su enlace desde su renglón.`}
            confirmLabel="Enviar enlaces"
            form={enrollAll.form(tenantSlug)}
            transform={() => ({ work_center_id: center.id })}
            preserveScroll
            destructive={false}
        />
    );
}
