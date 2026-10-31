import { useState } from 'react';
import DemoRequestDetail from '@/components/demos/demo-request-detail';
import DemoRequestItem from '@/components/demos/demo-request-item';
import type { DemoRequestRow } from '@/types';

/**
 * The requests of one page as an inbox (pantallas: Consola-Solicitudes): the list at the left, the one chosen in full
 * at the right. The newest is open to begin with.
 */
export default function DemoRequestsInbox({
    requests,
}: {
    requests: DemoRequestRow[];
}) {
    const [chosenId, setChosenId] = useState(requests[0]?.id);
    const chosen =
        requests.find((request) => request.id === chosenId) ?? requests[0];

    return (
        <div className="flex min-h-[520px] flex-wrap overflow-hidden rounded-xl border bg-card">
            <ul
                aria-label="Solicitudes"
                className="w-full border-b md:max-w-[360px] md:flex-[1_1_300px] md:border-r md:border-b-0"
            >
                {requests.map((request) => (
                    <DemoRequestItem
                        key={request.id}
                        request={request}
                        chosen={request.id === chosen?.id}
                        onChoose={() => setChosenId(request.id)}
                    />
                ))}
            </ul>
            {chosen !== undefined && <DemoRequestDetail request={chosen} />}
        </div>
    );
}
