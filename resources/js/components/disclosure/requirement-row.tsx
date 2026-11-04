import { Badge } from '@/components/ui/badge';
import { requirementVariant } from '@/lib/disclosure';
import type { BoardRequirement } from '@/types';

/** One requirement of a center: the numeral of the norm, what it asks, where it stands and, when it helps, why. */
export default function RequirementRow({
    requirement,
}: {
    requirement: BoardRequirement;
}) {
    return (
        <li className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-t py-3 first:border-t-0">
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="text-sm font-medium">
                    <span className="mr-2 text-muted-foreground tabular-nums">
                        {requirement.numeral}
                    </span>
                    {requirement.label}
                </span>
                {requirement.detail !== null && (
                    <span className="text-meta text-muted-foreground">
                        {requirement.detail}
                    </span>
                )}
            </div>
            <Badge variant={requirementVariant(requirement.state.value)}>
                {requirement.state.label}
            </Badge>
        </li>
    );
}
