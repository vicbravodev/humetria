import { Handle, Position } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { Spinner } from '@/components/ui/spinner';
import { useOrgCanvasActions } from '@/hooks/use-org-canvas-actions';
import type { MoreFlowNode } from '@/lib/org-chart/flow';

const HIDDEN_HANDLE =
    'pointer-events-none size-px! min-h-0! min-w-0! border-0! bg-transparent!';

function moreText(
    left: number | null,
    status: 'loading' | 'ready' | 'error',
): string {
    if (status === 'error') {
        return 'No se pudo cargar · Reintentar';
    }

    return left === null ? 'Ver más' : `Ver ${left} más`;
}

/** The card at the end of a long team: asks for the next page of reports, or tries again when the last one failed. */
export default function OrgMoreNode({ data }: NodeProps<MoreFlowNode>) {
    const { onMore } = useOrgCanvasActions();
    const text = moreText(data.left, data.status);

    return (
        <div className="w-[216px]">
            <Handle
                type="target"
                position={data.stacked ? Position.Left : Position.Top}
                className={HIDDEN_HANDLE}
                isConnectable={false}
            />
            <button
                type="button"
                disabled={data.status === 'loading'}
                aria-label={
                    data.status === 'error'
                        ? 'Reintentar: no se pudieron cargar más personas del equipo'
                        : `${text}: personas del equipo`
                }
                onClick={() => onMore(data.parentId)}
                className="nodrag flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-input bg-card text-sm font-medium text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                {data.status === 'loading' && <Spinner className="size-4" />}
                {text}
            </button>
        </div>
    );
}
