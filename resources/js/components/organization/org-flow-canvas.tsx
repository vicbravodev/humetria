import {
    Background,
    BackgroundVariant,
    Controls,
    MiniMap,
    ReactFlow,
    useReactFlow,
    useStore,
} from '@xyflow/react';
import type { Edge } from '@xyflow/react';
import type { DragEvent } from 'react';
import '@/components/organization/org-flow.css';
import OrgChartCamera from '@/components/organization/org-chart-camera';
import OrgCompanyNode from '@/components/organization/org-company-node';
import OrgMoreNode from '@/components/organization/org-more-node';
import OrgPersonNode from '@/components/organization/org-person-node';
import { FIT_ZOOM, viewportFor } from '@/lib/org-chart/camera';
import type { Box } from '@/lib/org-chart/camera';
import type { CameraRequest, FlowNode } from '@/lib/org-chart/flow';
import { useAppearance } from '@/hooks/use-appearance';
import { cspNonce } from '@/lib/csp-nonce';
import { cn } from '@/lib/utils';

const NODE_TYPES = {
    person: OrgPersonNode,
    company: OrgCompanyNode,
    more: OrgMoreNode,
};

const LABELS = {
    'controls.ariaLabel': 'Controles del organigrama',
    'controls.zoomIn.ariaLabel': 'Acercar',
    'controls.zoomOut.ariaLabel': 'Alejar',
    'controls.fitView.ariaLabel': 'Ajustar a la pantalla',
    'minimap.ariaLabel': 'Mapa del organigrama',
    'handle.ariaLabel': 'Punto de unión',
    'node.a11yDescription.default':
        'Pulsa Enter o la barra espaciadora para elegir a la persona.',
    'node.a11yDescription.keyboardDisabled':
        'Usa el botón de la persona para ver su panel.',
    'node.a11yDescription.ariaLiveMessage': ({
        x,
        y,
    }: {
        direction: string;
        x: number;
        y: number;
    }) => `Persona movida. Nueva posición, x: ${x}, y: ${y}`,
    'edge.a11yDescription.default': 'Conector entre dos personas.',
};

export type CanvasDrag = {
    onDrag: (id: string, at: { x: number; y: number }) => void;
    onStop: (id: string, at: { x: number; y: number }) => void;
    /** A person of the tray is over the canvas, at a point of it. */
    onTrayOver: (point: { x: number; y: number }) => void;
    onTrayDrop: (point: { x: number; y: number }) => void;
    onTrayLeave: () => void;
};

export default function OrgFlowCanvas({
    nodes,
    edges,
    editing,
    drag,
    camera,
}: {
    nodes: FlowNode[];
    edges: Edge[];
    editing: boolean;
    drag: CanvasDrag;
    camera: {
        request: CameraRequest;
        boundsOf: (ids: string[] | null) => Box | null;
    };
}) {
    const { screenToFlowPosition, setViewport } = useReactFlow();
    const width = useStore((state) => state.width);
    const height = useStore((state) => state.height);
    const { resolvedAppearance } = useAppearance();
    // «Ajustar» shows all of the chart that fits at a readable size, from the top, instead of shrinking it to a speck.
    const fit = () => {
        const box = camera.boundsOf(null);

        if (box !== null) {
            void setViewport(viewportFor(box, { width, height }, FIT_ZOOM), {
                duration: 250,
            });
        }
    };
    const point = (event: DragEvent) =>
        screenToFlowPosition({ x: event.clientX, y: event.clientY });

    return (
        <div
            className="h-full"
            onDragOver={(event) => {
                event.preventDefault();
                drag.onTrayOver(point(event));
            }}
            onDragLeave={drag.onTrayLeave}
            onDrop={(event) => {
                event.preventDefault();
                drag.onTrayDrop(point(event));
            }}
        >
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={NODE_TYPES}
                // The tokens of the app already follow its theme; React Flow must not impose a `light` of its own.
                colorMode={resolvedAppearance}
                className={cn('org-flow', editing && 'org-flow--editing')}
                nodesDraggable={editing}
                nodesConnectable={false}
                nodesFocusable={false}
                edgesFocusable={false}
                elementsSelectable={false}
                nodeDragThreshold={4}
                zoomOnDoubleClick={false}
                minZoom={0.15}
                maxZoom={1.5}
                onNodeDrag={(_, node) => drag.onDrag(node.id, node.position)}
                onNodeDragStop={(_, node) =>
                    drag.onStop(node.id, node.position)
                }
                ariaLabelConfig={LABELS}
                attributionPosition="bottom-center"
                nonce={cspNonce}
            >
                <Background
                    variant={BackgroundVariant.Dots}
                    gap={24}
                    size={1}
                    color="var(--border)"
                />
                <Controls
                    showInteractive={false}
                    position="bottom-left"
                    onFitView={fit}
                />
                <MiniMap
                    pannable
                    zoomable
                    position="bottom-right"
                    style={{ width: 160, height: 100 }}
                    className="max-md:hidden"
                />
                <OrgChartCamera
                    request={camera.request}
                    boundsOf={camera.boundsOf}
                />
            </ReactFlow>
        </div>
    );
}
