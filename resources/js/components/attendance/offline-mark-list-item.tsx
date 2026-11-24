import { Link } from '@inertiajs/react';
import OfflineBadge from '@/components/attendance/offline-badge';
import Initials from '@/components/humetria/initials';
import { Checkbox } from '@/components/ui/checkbox';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    correctionsQuery,
    shortMoment,
    waitingFor,
} from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import { flagText } from '@/lib/attendance/offline-marks';
import { cn } from '@/lib/utils';
import { index } from '@/routes/tenant/attendance/corrections';
import type { OfflineMarkRow } from '@/types';

/** On a phone the detail is below the list: bring it into view. Beside the list (lg) it already is. */
function showDetail(): void {
    if (!window.matchMedia('(min-width: 1024px)').matches) {
        document
            .getElementById('offline-mark-detail')
            ?.scrollIntoView({ block: 'start' });
    }
}

/**
 * One mark to verify: who, what and the time counted in a line, its flags in words and how long it waits. Choosing it puts
 * it in view without leaving the page; the box beside it (only for whoever resolves) selects it to verify with others.
 */
export default function OfflineMarkListItem({
    row,
    selected,
    checked,
    onCheck,
    canResolve,
    q,
    flags,
    page,
}: {
    row: OfflineMarkRow;
    selected: boolean;
    checked: boolean;
    onCheck: (checked: boolean) => void;
    canResolve: boolean;
    q: string;
    flags: string;
    page: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <li
            className={cn(
                'flex items-start border-l-[3px] border-transparent',
                selected && 'border-primary bg-accent',
            )}
        >
            {canResolve && (
                <label className="flex size-11 shrink-0 cursor-pointer items-start justify-center pt-4 pl-1">
                    <Checkbox
                        checked={checked}
                        onCheckedChange={(value) => onCheck(value === true)}
                        aria-label={`Elegir la marca de ${row.person.name}`}
                    />
                </label>
            )}
            <Link
                href={index(tenantSlug, {
                    query: {
                        ...correctionsQuery({ q, status: 'offline', flags }),
                        ...(page > 1 ? { page: String(page) } : {}),
                        request: row.id,
                    },
                })}
                only={['detail', 'selectedId']}
                preserveScroll
                preserveState
                onSuccess={showDetail}
                aria-current={selected ? 'true' : undefined}
                className={cn(
                    'flex min-w-0 flex-1 items-start gap-3 py-4 pr-[18px] text-left transition-colors duration-100 hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                    canResolve ? 'pl-1' : 'pl-[15px]',
                    selected && 'hover:bg-accent',
                )}
            >
                <Initials name={row.person.name} />
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                        <span className="font-semibold">{row.person.name}</span>
                        <OfflineBadge view="offline" />
                    </span>
                    <span className="text-sm font-medium">
                        {markName(row.type)} · {shortMoment(row.countedAt)}
                    </span>
                    {row.flags.length > 0 && (
                        <span className="text-sm text-muted-foreground">
                            {row.flags.map(flagText).join(' · ')}
                        </span>
                    )}
                    <span className="text-sm text-muted-foreground">
                        Llegó {waitingFor(row.ageDays)}
                    </span>
                </span>
            </Link>
        </li>
    );
}
