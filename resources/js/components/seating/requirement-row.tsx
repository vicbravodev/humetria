import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useSeatingHref } from '@/hooks/use-seating-href';
import { requirementCount, requirementSince } from '@/lib/seating/requirements';
import type { CenterRequirement } from '@/types';

const VARIANT = {
    met: 'success',
    warning: 'warning',
    missing: 'destructive',
} as const;

/** One requirement of the center: its number and words, where it stands, what is behind it and the way to put it right. */
export default function RequirementRow({
    workCenterId,
    requirement,
}: {
    workCenterId: string;
    requirement: CenterRequirement;
}) {
    const hrefOf = useSeatingHref(workCenterId);
    const count = requirementCount(requirement);
    const since = requirementSince(requirement);

    return (
        <li className="grid grid-cols-[2.5rem_minmax(0,1fr)] items-start gap-x-3 gap-y-2 py-3 first:pt-0 last:pb-0 @2xl:grid-cols-[3rem_minmax(0,1fr)_auto] @2xl:items-center @2xl:gap-4">
            <span className="text-sm font-medium text-muted-foreground tabular-nums">
                {requirement.number}
            </span>
            <div className="min-w-0">
                {requirement.state !== 'met' && requirement.tab !== null ? (
                    <Link
                        href={hrefOf(requirement.tab)}
                        className="touch-hit font-medium text-primary hover:underline"
                    >
                        {requirement.label}
                    </Link>
                ) : (
                    <p className="font-medium">{requirement.label}</p>
                )}
                {(count !== null || since !== null) && (
                    <p className="text-sm text-muted-foreground">
                        {[count, since]
                            .filter((part) => part !== null)
                            .join(' · ')}
                    </p>
                )}
            </div>
            <Badge
                className="col-start-2 @2xl:col-start-auto"
                variant={VARIANT[requirement.state]}
            >
                {requirement.stateLabel}
            </Badge>
        </li>
    );
}
