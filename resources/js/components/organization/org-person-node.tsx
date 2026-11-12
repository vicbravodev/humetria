import { Handle, Position } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { Spinner } from '@/components/ui/spinner';
import { useOrgCanvasActions } from '@/hooks/use-org-canvas-actions';
import { cardClasses, dropLabel } from '@/lib/org-chart/card-style';
import { nodeLabel, pillText } from '@/lib/org-chart/canvas-labels';
import type { PersonFlowNode } from '@/lib/org-chart/flow';
import { cn } from '@/lib/utils';

const HIDDEN_HANDLE =
    'pointer-events-none size-px! min-h-0! min-w-0! border-0! bg-transparent!';

/**
 * A person of the canvas: a button that opens their panel (name, job and how many report to them), with the pill
 * under it that opens or closes their branch. In editing mode the same card is what is dragged onto a new manager.
 */
export default function OrgPersonNode({ data }: NodeProps<PersonFlowNode>) {
    const { selected, onSelect, onToggle } = useOrgCanvasActions();
    const { node, open, tone, attention, status } = data.view;
    const tag = dropLabel(data.drop, node.name);

    return (
        <div className="relative w-[216px]">
            <Handle
                type="target"
                // A person in a column is reached from the left, by the rail that runs down it.
                position={data.stacked ? Position.Left : Position.Top}
                className={HIDDEN_HANDLE}
                isConnectable={false}
            />
            {tag !== null && (
                <span
                    role="presentation"
                    className="absolute bottom-full left-1/2 z-10 mb-1.5 -translate-x-1/2 rounded-md bg-primary px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-primary-foreground"
                >
                    {tag}
                </span>
            )}
            <button
                type="button"
                data-slot="org-node"
                aria-label={nodeLabel(node)}
                aria-pressed={selected === node.id}
                onClick={() => onSelect(node.id)}
                className={cardClasses({ tone, attention, drop: data.drop })}
            >
                <Initials name={node.name} className="size-9" />
                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span className="truncate text-sm leading-5 font-semibold">
                        {node.name}
                    </span>
                    <span
                        className={cn(
                            'truncate text-xs leading-4',
                            attention && tone !== 'selected'
                                ? 'text-warning-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {node.job_title}
                    </span>
                </span>
            </button>
            {open !== undefined && (
                <button
                    type="button"
                    aria-expanded={open}
                    aria-label={`${open ? 'Ocultar' : 'Ver'} el equipo de ${node.name}`}
                    onClick={() => onToggle(node.id)}
                    className="nodrag absolute top-full left-1/2 z-10 mt-1 inline-flex h-6 -translate-x-1/2 items-center gap-1 rounded-full border bg-card px-2.5 text-xs whitespace-nowrap text-muted-foreground transition-colors duration-150 before:absolute before:-inset-x-2 before:-inset-y-2.5 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    {status === 'loading' ? (
                        <Spinner className="size-3" />
                    ) : null}
                    {pillText(node, open)}
                    {open ? (
                        <ChevronUp aria-hidden className="size-3" />
                    ) : (
                        <ChevronDown aria-hidden className="size-3" />
                    )}
                </button>
            )}
            <Handle
                id="stack"
                type="source"
                position={Position.Bottom}
                // Where the rail of a column starts: under the left of the card.
                style={{ left: 12 }}
                className={HIDDEN_HANDLE}
                isConnectable={false}
            />
            <Handle
                type="source"
                position={Position.Bottom}
                // Under the pill, so the connector starts from it.
                className={cn(
                    HIDDEN_HANDLE,
                    open !== undefined && 'bottom-[-34px]!',
                )}
                isConnectable={false}
            />
        </div>
    );
}
