import { countOf } from '@/lib/plural';
import type { AgingBucket, AccountAging } from '@/types';

/** The four ages of a balance, as the strip and its legend name them. */
export const BUCKET_LABELS: Record<AgingBucket, string> = {
    current: 'Al corriente',
    '1_30': '1–30 días',
    '31_60': '31–60 días',
    over_60: 'Más de 60 días',
};

/** How old the oldest open statement of an account is: past due, or the days left to pay. */
export function agingLabel(aging: AccountAging | null): string {
    if (aging === null) {
        return 'Al corriente';
    }

    return aging.bucket === 'current'
        ? `Vence en ${countOf(aging.days, 'día', 'días')}`
        : `${countOf(aging.days, 'día', 'días')} vencido`;
}

/** The badge of that age: fine while it is not due, a warning the first 30 days past due, an alarm after. */
export function agingBadge(
    aging: AccountAging | null,
): 'success' | 'warning' | 'destructive' {
    if (aging === null || aging.bucket === 'current') {
        return 'success';
    }

    return aging.bucket === '1_30' ? 'warning' : 'destructive';
}

export type SuggestedAction = 'collect' | 'remind' | 'view';

/**
 * What to do next with an account: collect after 30 days past due, remind before, look while it is not due; nothing when it
 * owes nothing. Whoever may not remind nor record payments (support) only looks.
 */
export function suggestedAction(
    aging: AccountAging | null,
    canManage = true,
): SuggestedAction | null {
    if (aging === null) {
        return null;
    }

    if (aging.bucket === 'current' || !canManage) {
        return 'view';
    }

    return aging.bucket === '1_30' ? 'remind' : 'collect';
}
