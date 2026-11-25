import type {
    OtherScenario,
    RaiseUnit,
} from '@/lib/compensation/scenario-draft';
import type {
    AssumptionField,
    Paginated,
    ReportShareRow,
    PayBasis,
    ScenarioSource,
} from '@/types';

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
    /** What it adds to a year at the new salary, retroactivity aside: the steps of the cost add up to the total. */
    recurring_cents: number;
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
    /** The work risk premium is the one of class I because the company has not captured its own (an average payroll only). */
    class_one_premium: boolean;
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

/** What a company reads first, from the engine: every ratio is in basis points, hundredths or cents. */
export type ReportImpact = {
    /** What the salary alone grows: 600 is 6 %. */
    salary_growth_bp: number;
    /** What the payroll with its benefits and quotas grows. */
    payroll_growth_bp: number;
    /** What each peso of salary costs with the benefits and quotas it drags: 127 is $1.27. */
    drag_hundredths: number;
    inflation_bp: number;
    /** The raise of the salary against inflation. */
    real_raise_bp: number;
    per_person_month_cents: number;
};

/** The raise at one level of the ladder, as it costs. */
export type ReportRung = {
    /** "6 %", or "5 % · 3 %". */
    raise_text: string;
    current: boolean;
    first_year_cents: number;
    recurring_cents: number;
    salary_growth_bp: number;
    payroll_growth_bp: number;
    per_person_month_cents: number;
};

/** One average worker walked through the cost, all in cents for the worker (a year unless it says month). */
export type ReportExample = {
    workers: number;
    /** The monthly payroll of the base example: every worker's salary. */
    monthly_payroll_cents: number;
    monthly_salary_cents: number;
    monthly_raise_cents: number;
    aguinaldo_days: string;
    vacation_days: string;
    vacation_premium_percent: string;
    salary_extra_year_cents: number;
    bonus_extra_year_cents: number;
    premium_extra_year_cents: number;
    /** The integration factor, "1.0493". */
    factor: string;
    /** The daily SBC before and after. */
    sbc_before_cents: number;
    sbc_after_cents: number;
    /** The quotas of the SBC: IMSS, retirement, severance and Infonavit. */
    contributions_extra_year_cents: number;
    /** The state's payroll tax; 0 when it is not calculated (no state). */
    payroll_tax_extra_year_cents: number;
    total_extra_year_cents: number;
    total_extra_month_cents: number;
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
        /** What it is costed on: the people of the company, or an average payroll typed in the scenario. */
        source: ScenarioSource;
        /** How its salaries are paid: a month is 30 days of the daily salary when paid monthly, 30.4 when paid by the day. */
        pay_basis: PayBasis;
        /** The average payroll, null for a scenario of the roster. */
        average: {
            workers: number;
            /** "$15,000.00". */
            monthly_salary_text: string;
            tenure_years: number;
            /** "Sindicalizados" or "Confianza". */
            group: string;
        } | null;
    };
    coverage: {
        total: number;
        costed: number;
        /** Employee numbers of the people who cannot be costed. */
        missing: string[];
        missing_more: number;
        missing_without_number: number;
        /** How many people lack each thing; one can lack several. */
        causes: {
            without_grade: number;
            without_hire_date: number;
            without_group: number;
        };
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
    impact: ReportImpact;
    /** The raise two points apart, the scenario's own among them; none for a fixed amount or by a link. */
    ladder: ReportRung[];
    /** One worker of an average payroll; null for a scenario of the roster. */
    example: ReportExample | null;
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
    /** Whether whoever opens it manages the contract: it keeps the shortcuts to edit and to complete the data, annex or not. */
    can_manage: boolean;
    /** The links to the report that still open; null for whoever cannot share it. */
    shares: Paginated<ReportShareRow> | null;
};
