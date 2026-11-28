import DemoFactRow from '@/components/demos/demo-fact-row';
import type { DemoRequestRow } from '@/types';

/**
 * Where the request came from, as lines of its panel: the origin with the channel it arrived by under it, and the
 * first contact when it was another one. No data is not the same as direct traffic.
 */
export default function DemoRequestChannels({
    request,
}: {
    request: DemoRequestRow;
}) {
    return (
        <>
            <DemoFactRow term="Origen">
                {request.origin}
                <span className="block text-sm font-normal text-muted-foreground">
                    {request.arrived_by ?? 'No significa tráfico directo.'}
                </span>
            </DemoFactRow>
            {request.first_contact !== null && (
                <DemoFactRow term="Primer contacto">
                    {request.first_contact}
                </DemoFactRow>
            )}
        </>
    );
}
