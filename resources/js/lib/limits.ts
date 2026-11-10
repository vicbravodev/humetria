import type { LimitRow } from '@/types';

/** "Quedan 8 lugares", "Quedan 0 lugares: no puede agregar más" or, above the limit, how many it has over. */
export function limitLine(
    limit: Pick<LimitRow, 'used' | 'amount' | 'remaining'>,
): string {
    if (limit.used > limit.amount) {
        const over = limit.used - limit.amount;

        return `Tiene ${over} ${over === 1 ? 'lugar' : 'lugares'} de más: conserva lo que tiene y no puede agregar.`;
    }

    if (limit.remaining === 0) {
        return 'Sin lugares: no puede agregar más hasta ampliar el plan.';
    }

    return `${limit.remaining === 1 ? 'Queda' : 'Quedan'} ${limit.remaining} ${limit.remaining === 1 ? 'lugar' : 'lugares'}.`;
}
