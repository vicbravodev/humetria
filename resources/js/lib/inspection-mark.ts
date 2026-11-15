import type { InspectionItem } from '@/types';

/** The dashed ring of what is not shown yet: neutral when nothing is registered to judge it by, red when it is missing. */
export function missingMark(state: InspectionItem['state']): {
    label: string;
    className: string;
} {
    return state === 'pending'
        ? { label: 'Sin registros', className: 'border-muted-foreground' }
        : { label: 'Falta', className: 'border-risk-alto' };
}
