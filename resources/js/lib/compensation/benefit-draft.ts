import { countOf } from '@/lib/plural';

/** What a benefit that is being typed costs today, as the server wrote it (decision A14). */
export type BenefitPreview = {
    people: number;
    yearly_text: string;
    per_person_month_text: string;
};

/** «Como el aguinaldo»: an example of each way of paying a benefit (board V4-Comp-Prestacion). */
export const KIND_HINTS: Record<string, string> = {
    salary_days: 'Como el aguinaldo',
    salary_percent: 'Como la prima vacacional',
    fixed_amount: 'Como los vales',
    uma_multiple: 'En veces la UMA, como algunos topes de ley',
};

/** What each answer to «¿Cuenta para las cuotas del IMSS?» means. */
export const SBC_HINTS: Record<string, string> = {
    always: 'Se suma al salario con el que cotizas',
    up_to_cap: 'Solo lo que pase del límite que indiques',
    never: 'No integra al salario de cotización',
};

/** The server's answer, or null when it is not one. */
export function parseBenefitPreview(value: unknown): BenefitPreview | null {
    if (
        typeof value !== 'object' ||
        value === null ||
        !('people' in value) ||
        !('yearly_text' in value) ||
        !('per_person_month_text' in value)
    ) {
        return null;
    }

    const { people, yearly_text, per_person_month_text } = value;

    return typeof people === 'number' &&
        typeof yearly_text === 'string' &&
        typeof per_person_month_text === 'string'
        ? { people, yearly_text, per_person_month_text }
        : null;
}

/** "al año, a 68 sindicalizados, con sus cuotas": who the cost of today reaches. */
export function reachText(people: number, group: string): string {
    const who =
        group === 'unionized'
            ? countOf(people, 'sindicalizado', 'sindicalizados')
            : group === 'trusted'
              ? countOf(people, 'persona de confianza', 'personas de confianza')
              : countOf(people, 'persona', 'personas');

    return `al año, a ${who}, con sus cuotas`;
}
