import { useRef, useState } from 'react';
import DemoRequestDetail from '@/components/demos/demo-request-detail';
import DemoRequestItem from '@/components/demos/demo-request-item';
import { useIsMobile } from '@/hooks/use-mobile';
import type { DemoRequestRow } from '@/types';

/**
 * The requests of one page as a list, with the one chosen in the same fixed panel as the funnel (tablero Demos). The
 * newest is chosen to begin with. Below 1280 px the panel sits under the list; on a phone, choosing one takes the focus
 * there, so the screen and a screen reader land on it.
 */
export default function DemoRequestsInbox({
    requests,
    canManage,
}: {
    requests: DemoRequestRow[];
    canManage: boolean;
}) {
    const [chosenId, setChosenId] = useState(requests[0]?.id);
    const chosen =
        requests.find((request) => request.id === chosenId) ?? requests[0];
    const detail = useRef<HTMLElement>(null);
    const stacked = useIsMobile();
    const choose = (id: string) => {
        setChosenId(id);

        if (stacked) {
            detail.current?.focus();
        }
    };

    return (
        <div className="flex flex-col gap-6 xl:flex-row xl:items-start">
            <ul
                aria-label="Solicitudes"
                className="min-w-0 flex-1 overflow-hidden card"
            >
                {requests.map((request) => (
                    <DemoRequestItem
                        key={request.id}
                        request={request}
                        chosen={request.id === chosen?.id}
                        onChoose={() => choose(request.id)}
                    />
                ))}
            </ul>
            {chosen !== undefined && (
                <DemoRequestDetail
                    ref={detail}
                    request={chosen}
                    canManage={canManage}
                />
            )}
        </div>
    );
}
