import DemoFactRow from '@/components/demos/demo-fact-row';
import DemoRequestChannels from '@/components/demos/demo-request-channels';
import { profileText, toolsText, workforceText } from '@/lib/demo-requests';
import { readablePhone } from '@/lib/phone';
import { whatsappUrl } from '@/lib/whatsapp-url';
import type { DemoRequestRow } from '@/types';

/**
 * The request's data in its panel, in the board's order (Contacto, Correo, Teléfono, Empleados, Origen, Responsable,
 * Recibida), then what it asks about and, once discarded, why.
 */
export default function DemoRequestFacts({
    request,
}: {
    request: DemoRequestRow;
}) {
    return (
        <dl>
            <DemoFactRow term="Contacto">{request.name}</DemoFactRow>
            {/* Plain, as the board writes it: «Responder por correo» is the way to write. */}
            <DemoFactRow term="Correo">
                <span className="break-all">{request.email}</span>
            </DemoFactRow>
            <DemoFactRow term="Teléfono">
                <a
                    href={whatsappUrl(request.whatsapp)}
                    target="_blank"
                    rel="noreferrer"
                    className="relative text-primary tabular-nums underline-offset-4 before:absolute before:inset-x-0 before:-inset-y-3.5 hover:underline"
                >
                    {readablePhone(request.whatsapp)}
                </a>
                <span className="block text-sm font-normal text-muted-foreground">
                    WhatsApp
                </span>
            </DemoFactRow>
            <DemoFactRow term="Empleados">
                {workforceText(request.workforce)}
                <span className="block text-sm font-normal text-muted-foreground">
                    {profileText(request.profile)}
                </span>
            </DemoFactRow>
            <DemoRequestChannels request={request} />
            <DemoFactRow term="Responsable">
                {request.owner?.name ?? 'Sin asignar'}
            </DemoFactRow>
            <DemoFactRow term="Recibida">{request.received_at}</DemoFactRow>
            <DemoFactRow term="Le interesa">
                {toolsText(request.tools)}
            </DemoFactRow>
            {request.status === 'discarded' &&
                request.discard_reason !== null && (
                    <DemoFactRow term="Motivo">
                        {request.discard_reason}
                    </DemoFactRow>
                )}
        </dl>
    );
}
