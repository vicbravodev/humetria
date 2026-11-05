import LinkUnavailable from '@/components/link-unavailable';

/** The link opens nothing, whatever the cause (pantalla: Enlace-Vencido): it says nothing about who or what exists. */
export default function PortalUnavailable() {
    return (
        <div className="flex flex-1 items-center justify-center py-8">
            {/* The portal link is permanent, so a newer one means this one was replaced. */}
            <LinkUnavailable>
                Revise que lo haya abierto completo desde su correo. Si su
                empresa le envió uno nuevo, use el más reciente. Si el problema
                sigue, comuníquese con el área de Recursos Humanos.
            </LinkUnavailable>
        </div>
    );
}
