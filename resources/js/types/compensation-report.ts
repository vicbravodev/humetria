import type {
    OtherScenario,
    RaiseUnit,
} from '@/lib/compensation/scenario-draft';
import type { AssumptionField, Paginated, ReportShareRow } from '@/types';

/** The cost of one slice of the roster (an area, a category and level, a group) over the first 12 months, written as money. */
export type ReportCut = {
    label: string;
    people: number;
    before_text: string;
    after_text: string;
    delta_text: string;
};

/** A concept of the cost cascade over the first 12 months, written as money. */
export type ReportConcept = {
    type: 'salary' | 'benefit' | 'contribution';
    key: string;
    label: string;
    before_text: string;
    after_text: string;
    delta_text: string;
    first_year_text: string;
    /** What it adds to the first year, retroactivity included, for the cascade. */
    first_year_cents: number;
};

/** What each person of a category and level receives more a month with the proposal, from the tabulator salary. */
export type ReportRaise = {
    label: string;
    group: 'unionized' | 'trusted';
    people: number;
    monthly_raise_cents: number;
};

/** One year of the projection: what the company pays without the change and with it. */
export type ReportYear = {
    year: number;
    /** `AAAA-MM-DD`. */
    effective_on: string;
    people: number;
    before_text: string;
    after_text: string;
    delta_text: string;
    retroactive_text: string | null;
    /** What the change costs that year, retroactivity included. */
    cost_cents: number;
};

/** What the report says before the numbers can be trusted. */
export type ReportWarnings = {
    individual: { count: number; percent: string; over_tenth: boolean };
    uncosted: { label: string; people: number }[];
    /** The employer lines that could not be calculated. */
    uncalculated: string[];
    /** The fiscal values carried past their vigencia. */
    stale: string[];
};

/** The worker's gross against the company's cost, per concept. */
export type ReportPersonValue = {
    rows: {
        label: string;
        type: 'salary' | 'benefit' | 'contribution';
        person_text: string | null;
        company_text: string;
        exempt: boolean;
    }[];
    person_total_text: string;
    company_total_text: string;
};

/** The internal report of a scenario, as the page takes it. */
export type ScenarioReport = {
    scenario: {
        id: string;
        name: string;
        effective_on: string;
        horizon_years: number;
        retroactive_months: number;
        unionized_raise: string;
        trusted_raise: string | null;
        /** "5 % sindicalizados · 3 % confianza" or "$608 al mes a sindicalizados y confianza". */
        raise_text: string;
        raise_unit: RaiseUnit;
    };
    coverage: {
        total: number;
        costed: number;
        /** Employee numbers of the people who cannot be costed. */
        missing: string[];
        missing_more: number;
        missing_without_number: number;
    };
    headline: {
        people: number;
        retroactive_months: number;
        first_year_text: string;
        recurring_text: string;
        retroactive_text: string;
        before_text: string;
        after_text: string;
        first_year_cents: number;
        recurring_cents: number;
        retroactive_cents: number;
        before_cents: number;
        after_cents: number;
    };
    concepts: ReportConcept[];
    months: {
        month: string;
        before_text: string;
        after_text: string;
        delta_text: string;
    }[];
    cuts: { areas: ReportCut[]; grades: ReportCut[]; groups: ReportCut[] };
    /** Sindicalizados first, the most first; by a link, only the categories over the minimum. */
    raises: ReportRaise[];
    /** The other active scenarios of the account (never the union's, D1); none by a link. */
    others: OtherScenario[];
    projection: ReportYear[];
    seniority: {
        people: number;
        before_text: string;
        after_text: string;
        delta_text: string;
    };
    sensitivity: {
        one_point_text: string;
        base_text: string;
        minus_text: string;
        plus_text: string;
    };
    assumptions: Omit<AssumptionField, 'key'>[];
    warnings: ReportWarnings;
    person_value: ReportPersonValue;
    can_annex: boolean;
    /** The links to the report that still open; null for whoever cannot share it. */
    shares: Paginated<ReportShareRow> | null;
};
