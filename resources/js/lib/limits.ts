import type { LimitRow, PlanLimit } from '@/types';

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

type Noun = { singular: string; plural: string };

const counted = (amount: number, noun: Noun): string =>
    `${amount} ${amount === 1 ? noun.singular : noun.plural}`;

/**
 * What a company's own page says about its plan: how much of it is used while there is room, and, at the limit or above it,
 * that it has to ask Humetria for an expansion (`full` turns the line into a notice).
 */
export function planUsage(
    limit: PlanLimit,
    noun: Noun,
): { text: string; full: boolean } {
    if (limit.used > limit.amount) {
        return {
            text: `Tu plan cubre ${counted(limit.amount, noun)} y tienes ${limit.used}. Pide una ampliación a Humetria.`,
            full: true,
        };
    }

    if (limit.remaining === 0) {
        return {
            text: `Tu plan cubre ${counted(limit.amount, noun)} y ya llegaste al tope. Pide una ampliación a Humetria.`,
            full: true,
        };
    }

    return {
        text: `Usas ${limit.used} de ${counted(limit.amount, noun)} de tu plan.`,
        full: false,
    };
}
