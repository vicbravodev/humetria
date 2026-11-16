import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { inviteCenter } from '@/routes/tenant/worker-access';
import type { WorkerAccessPageProps } from '@/types';

/** Invites every person of the center with no key and no invitation that still opens; who has no email is left for RH, one by one. */
export default function InviteCenterButton({
    center,
    waiting,
    linkHours,
}: {
    center: NonNullable<WorkerAccessPageProps['center']>;
    /** People of the center with no key in use. */
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
                    Invitar a todos los que no tienen llave
                </Button>
            }
            title={`¿Invitar a ${waiting === 1 ? 'la persona' : `las ${waiting} personas`} de ${center.name} sin llave?`}
            description={`Cada quien recibe su invitación en su correo y la abre en su teléfono; funciona una vez y vence en ${linkHours} h. A quien ya tenga una invitación vigente no se le envía otra (reenvíasela desde su renglón) y a quien no tenga correo tampoco: crea su enlace desde su renglón.`}
            confirmLabel="Enviar invitaciones"
            form={inviteCenter.form(tenantSlug)}
            transform={() => ({ work_center_id: center.id })}
            preserveScroll
            destructive={false}
        />
    );
}
