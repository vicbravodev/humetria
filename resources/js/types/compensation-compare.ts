/** A scenario in the comparison, from the cheapest, with its totals in whole pesos. */
export type CompareScenario = {
    id: string;
    name: string;
    raise_text: string;
    retroactive_months: number;
    people: number;
    first_year_text: string;
    recurring_text: string;
    /** "+$313,614" over the cheapest; null for the cheapest. */
    above_lowest_text: string | null;
};

/** A concept of the cost with what each compared scenario changes in it, in the order of the scenarios. */
export type CompareRow = {
    type: 'salary' | 'benefit' | 'contribution';
    key: string;
    label: string;
    cells: string[];
    /** The dearest against the one before it: "+$222,980". */
    difference_text: string;
};

/** The totals of each scenario and, for the two dearest, how far apart they are. */
export type CompareTotals = {
    first_year: string[];
    recurring: string[];
    first_year_difference: string;
    recurring_difference: string;
};

/** «La distancia a negociar»: between the two dearest, and the concept that makes most of it. */
export type CompareDistance = {
    from: string;
    to: string;
    first_year_text: string;
    recurring_text: string;
    mostly: string | null;
};

/** «Cómo se mueve»: about what a point to each group and a month of retroactivity cost, on the dearest. */
export type CompareMoves = {
    unionized_point_text: string;
    trusted_point_text: string | null;
    retroactive_month_text: string;
};

/** The point in between of the two dearest, as the editor types a new scenario. */
export type CompareMidpoint = {
    raise_unit: 'percent' | 'amount';
    unionized_raise: string;
    trusted_raise: string;
    retroactive_months: number;
};
