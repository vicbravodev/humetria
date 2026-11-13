import { useReactFlow, useStore } from '@xyflow/react';
import { useEffect, useRef } from 'react';
import { readableViewport, viewportFor } from '@/lib/org-chart/camera';
import type { Box } from '@/lib/org-chart/camera';
import type { CameraRequest } from '@/lib/org-chart/flow';

/**
 * Takes the view to the people asked, once per request: as large as fits, from the top, under the toolbar. The layout
 * knows where every card is, so it does not wait for the cards to be measured. A canvas that changes size afterwards
 * keeps the view the person has.
 */
export default function OrgChartCamera({
    request,
    boundsOf,
}: {
    request: CameraRequest;
    /** Where the cards of those people are now (all of them when null); null when there are none. */
    boundsOf: (ids: string[] | null) => Box | null;
}) {
    const { setViewport } = useReactFlow();
    const width = useStore((state) => state.width);
    const height = useStore((state) => state.height);
    const handled = useRef<CameraRequest>(null);

    useEffect(() => {
        if (request === null || width === 0 || handled.current === request) {
            return;
        }

        const frame = requestAnimationFrame(() => {
            const box = boundsOf(request.ids);

            handled.current = request;

            const top =
                request.readable === undefined
                    ? null
                    : boundsOf(request.readable);

            if (box !== null) {
                const screen = { width, height };

                void setViewport(
                    top === null
                        ? viewportFor(box, screen)
                        : readableViewport(box, top, screen),
                    { duration: 250 },
                );
            }
        });

        return () => cancelAnimationFrame(frame);
    }, [request, boundsOf, width, height, setViewport]);

    return null;
}
