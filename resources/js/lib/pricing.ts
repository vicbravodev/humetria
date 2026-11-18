/** The most people the calculator takes: past it the count is still "quote by hand", never a number that breaks the page. */
const MAX_PEOPLE = 100_000;

/** What a company pays a month before VAT: the rate per person, never less than the minimum of the plan. */
export function monthlyCost(
    rate: number,
    minimum: number,
    people: number,
): number {
    return Math.max(rate * people, minimum);
}

/** A whole amount of pesos as the landing writes it: «$1,990». The currency (MXN) is said beside it, not inside. */
export function formatPesos(amount: number): string {
    return `$${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 0 }).format(amount)}`;
}

/** The count of people typed in the calculator: a whole number of at least one, or null when it is not one. */
export function parsePeople(text: string): number | null {
    const value = text.trim();

    if (!/^\d+$/.test(value)) {
        return null;
    }

    const people = Number(value);

    return people < 1 ? null : Math.min(people, MAX_PEOPLE);
}

/** The ends of the slider: from a small team up to just past where the catalog stops having a table (100), so the last notch reads "quote". */
export const SLIDER_MIN = 5;
export const SLIDER_MAX = 120;

/** Where the thumb sits for a count of people: the count itself, held inside the track. */
export function sliderValue(people: number): number {
    return Math.min(Math.max(people, SLIDER_MIN), SLIDER_MAX);
}
