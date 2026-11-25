import { Badge } from '@/components/ui/badge';
import type { DemoRequestRow } from '@/types';

/** Where the request stands, and when its demo is or why it was discarded. */
export default function DemoRequestStanding({
    request,
}: {
    request: DemoRequestRow;
}) {
    const detail =
        request.status === 'scheduled' && request.scheduled_for !== null
            ? `Demo el ${request.scheduled_for}`
            : request.status === 'discarded' && request.discard_reason !== null
              ? `Motivo: ${request.discard_reason}`
              : null;

    return (
        <div className="flex flex-col gap-1">
            <h3 className="flex items-center gap-2 text-caption text-muted-foreground">
                Seguimiento
                <Badge
                    variant={
                        request.status === 'converted' ? 'default' : 'outline'
                    }
                >
                    {request.status_label}
                </Badge>
            </h3>
            {detail !== null && <p className="text-sm">{detail}</p>}
        </div>
    );
}
