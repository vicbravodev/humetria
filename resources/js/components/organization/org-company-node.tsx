import { Handle, Position } from '@xyflow/react';
import type { NodeProps } from '@xyflow/react';
import { Building2 } from 'lucide-react';
import type { CompanyFlowNode } from '@/lib/org-chart/flow';
import { countOf } from '@/lib/plural';

const HIDDEN_HANDLE =
    'pointer-events-none size-px! min-h-0! min-w-0! border-0! bg-transparent!';

/**
 * The top of a company with several people at the top: not a person (it has no avatar and nobody can be dropped on
 * it) but the whole company, as a dashed card the heads hang from.
 */
export default function OrgCompanyNode({ data }: NodeProps<CompanyFlowNode>) {
    return (
        <div
            data-slot="org-company"
            className="flex h-[72px] w-[216px] items-center gap-3 rounded-xl border border-dashed border-input bg-card p-3"
        >
            <span
                aria-hidden
                className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
            >
                <Building2 className="size-5" />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="truncate text-sm leading-5 font-semibold">
                    {data.name}
                </span>
                <span className="text-xs leading-4 text-muted-foreground">
                    {countOf(data.people, 'persona', 'personas')}
                </span>
            </span>
            <Handle
                type="source"
                position={Position.Bottom}
                className={HIDDEN_HANDLE}
                isConnectable={false}
            />
        </div>
    );
}
