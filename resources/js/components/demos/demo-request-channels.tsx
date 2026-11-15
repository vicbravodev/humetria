import type { DemoRequestRow } from '@/types';

/** Where the request came from, as two entries of the detail's list: no data is not the same as direct traffic. */
export default function DemoRequestChannels({
    request,
}: {
    request: DemoRequestRow;
}) {
    return (
        <>
            <div className="flex flex-col gap-0.5">
                <dt className="text-caption text-muted-foreground">
                    Llegó por
                </dt>
                <dd className="text-sm font-medium">
                    {request.arrived_by ?? (
                        <>
                            <span className="text-muted-foreground">
                                Sin dato
                            </span>
                            <span className="block text-caption font-normal text-muted-foreground">
                                No significa tráfico directo.
                            </span>
                        </>
                    )}
                </dd>
            </div>
            {request.first_contact !== null && (
                <div className="flex flex-col gap-0.5">
                    <dt className="text-caption text-muted-foreground">
                        Primer contacto
                    </dt>
                    <dd className="text-sm font-medium">
                        {request.first_contact}
                    </dd>
                </div>
            )}
        </>
    );
}
