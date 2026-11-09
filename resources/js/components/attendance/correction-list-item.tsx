import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import {
    correctionsQuery,
    shortMoment,
    waitingFor,
} from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cn } from '@/lib/utils';
import { index } from '@/routes/tenant/attendance/corrections';
import type { CorrectionRow, CorrectionStatus } from '@/types';

const RESOLVED = {
    approved: { label: 'Aprobada', variant: 'success' },
    rejected: { label: 'Rechazada', variant: 'secondary' },
} as const;

/** On a phone the detail is below the list: bring it into view. Beside the list (lg) it already is. */
function showDetail(): void {
    if (!window.matchMedia('(min-width: 1024px)').matches) {
        document
            .getElementById('correction-detail')
            ?.scrollIntoView({ block: 'start' });
    }
}

/** One request of the queue: who, what they ask and why in a line; choosing it puts it in view without leaving the page. */
export default function CorrectionListItem({
    correction,
    selected,
    status,
    q,
    page,
}: {
    correction: CorrectionRow;
    selected: boolean;
    status: CorrectionStatus;
    q: string;
    page: number;
}) {
    const tenantSlug = useTenantSlug();
    const chip =
        correction.status === 'pending'
            ? {
                  label: `Espera desde ${waitingFor(correction.ageDays)}`,
                  variant: 'warning' as const,
              }
            : RESOLVED[correction.status];

    return (
        <li>
            <Link
                href={index(tenantSlug, {
                    query: {
                        ...correctionsQuery({ q, status }),
                        ...(page > 1 ? { page: String(page) } : {}),
                        request: correction.id,
                    },
                })}
                only={['detail', 'selectedId']}
                preserveScroll
                preserveState
                onSuccess={showDetail}
                aria-current={selected ? 'true' : undefined}
                className={cn(
                    'flex items-start gap-3 border-l-[3px] border-transparent px-[18px] py-4 text-left transition-colors duration-100 hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                    selected && 'border-primary bg-accent hover:bg-accent',
                )}
            >
                <Initials name={correction.person.name} />
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                        <span className="font-semibold">
                            {correction.person.name}
                        </span>
                        <Badge variant={chip.variant}>{chip.label}</Badge>
                    </span>
                    <span className="text-sm font-medium">
                        {markName(correction.type)} ·{' '}
                        {shortMoment(correction.requestedAt)}
                    </span>
                    <span className="line-clamp-2 text-sm text-muted-foreground">
                        {correction.reason}
                    </span>
                </span>
            </Link>
        </li>
    );
}
