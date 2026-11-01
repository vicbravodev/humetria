import { Link2Off } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';

/** The link opens nothing, whatever the cause (pantalla: Enlace-Vencido): it says nothing about who or what exists. */
export default function PortalUnavailable() {
    return (
        <div className="flex flex-1 items-center justify-center py-8">
            <StatusMessage
                icon={Link2Off}
                caption="Enlace no válido"
                title="Este enlace no es válido"
                // The portal link is permanent, so a newer one means this one was replaced.
            >
                Revise que lo haya abierto completo desde su correo. Si su
                empresa le envió uno nuevo, use el más reciente. Si el problema
                sigue, comuníquese con el área de Recursos Humanos.
            </StatusMessage>
        </div>
    );
}
