import { ReactFlowProvider } from '@xyflow/react';
import type { Edge } from '@xyflow/react';
import OrgFlowCanvas from '@/components/organization/org-flow-canvas';
import type { CanvasDrag } from '@/components/organization/org-flow-canvas';
import { OrgCanvasContext } from '@/hooks/use-org-canvas-actions';
import type { OrgCanvasActions } from '@/hooks/use-org-canvas-actions';
import type { Box } from '@/lib/org-chart/camera';
import type { CameraRequest, FlowNode } from '@/lib/org-chart/flow';

/**
 * The org chart as a canvas (React Flow): a dotted ground, the cards where dagre put them, elbow connectors, zoom
 * controls and a minimap. The cards reach what they can do through context, since they live inside React Flow.
 */
export default function OrgChartCanvas({
    actions,
    ...rest
}: {
    actions: OrgCanvasActions;
    nodes: FlowNode[];
    edges: Edge[];
    editing: boolean;
    drag: CanvasDrag;
    camera: {
        request: CameraRequest;
        boundsOf: (ids: string[] | null) => Box | null;
    };
}) {
    return (
        <OrgCanvasContext value={actions}>
            <ReactFlowProvider>
                <OrgFlowCanvas {...rest} />
            </ReactFlowProvider>
        </OrgCanvasContext>
    );
}
