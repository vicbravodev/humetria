import { Pause, Play, RefreshCw } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { pause, resume, rotate } from '@/routes/tenant/complaints/channel';
import type { ChannelStatus } from '@/types';

/** What can be done to an activated channel: pause it or open it again, and change its link. */
export default function ChannelActions({ status }: { status: ChannelStatus }) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            {status === 'paused' ? (
                <ConfirmDialog
                    destructive={false}
                    title="¿Volver a recibir quejas?"
                    description="El canal abre otra vez con el mismo enlace y el mismo cartel."
                    confirmLabel="Reanudar el canal"
                    form={resume.form(tenantSlug)}
                    trigger={
                        <Button variant="outline">
                            <Play aria-hidden />
                            Reanudar
                        </Button>
                    }
                />
            ) : (
                <ConfirmDialog
                    title="¿Pausar el canal?"
                    description="Mientras esté en pausa nadie puede presentar quejas nuevas: la página dirá que no está disponible. Lo que ya llegó sigue en atención."
                    confirmLabel="Pausar el canal"
                    form={pause.form(tenantSlug)}
                    trigger={
                        <Button variant="outline">
                            <Pause aria-hidden />
                            Pausar
                        </Button>
                    }
                />
            )}
            <ConfirmDialog
                title="¿Cambiar el enlace?"
                description="El enlace y el cartel que ya imprimiste dejan de funcionar para siempre. Úsalo si crees que el enlace llegó a quien no debía."
                confirmLabel="Cambiar el enlace"
                form={rotate.form(tenantSlug)}
                trigger={
                    <Button variant="outline">
                        <RefreshCw aria-hidden />
                        Cambiar enlace
                    </Button>
                }
            />
        </>
    );
}
