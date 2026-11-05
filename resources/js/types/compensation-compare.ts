/** A scenario in the comparison, with its totals written as money. */
export type CompareScenario = {
    id: string;
    name: string;
    people: number;
    first_year_text: string;
    recurring_text: string;
};

/** A concept of the cost with what each compared scenario changes in it, in the order of the scenarios. */
export type CompareRow = {
    type: 'salary' | 'benefit' | 'contribution';
    key: string;
    label: string;
    cells: string[];
};
