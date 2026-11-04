import type { TermChangeForm } from '@/lib/compensation/scenarios';
import type { ChoiceOption } from '@/types/organization';

export type {
    BenefitCounts,
    BenefitFilters,
    TenureFormRow,
} from '@/lib/compensation/benefits';
export type {
    SalaryFilters,
    SalaryStanding,
} from '@/lib/compensation/salaries';
export type {
    ScenarioCounts,
    ScenarioFilters,
    TermChangeForm,
} from '@/lib/compensation/scenarios';

/** A row of a seniority table as the server sends it: the value to edit and the value written with its unit. */
export type TenureRow = {
    from_years: number;
    value: string;
    value_text: string;
};

/** The terms of a benefit from a date on. `warning` is set when they fall below what the law sets. */
export type BenefitTerm = {
    id: string;
    /** `AAAA-MM-DD`. */
    effective_from: string;
    value: string;
    value_text: string;
    tenure_table: TenureRow[] | null;
    warning: string | null;
};

/** One benefit of the catalog as the «Prestaciones» list shows it, with its terms, latest vigencia first. */
export type BenefitRow = {
    id: string;
    name: string;
    is_base: boolean;
    kind: string;
    kind_label: string;
    /** What the value is of: "días de salario", "% del salario", "pesos", "UMA". */
    unit: string;
    frequency: string;
    frequency_label: string;
    /** Empty for everyone. */
    group: string;
    group_label: string;
    /** The categories it is for; none for every category. */
    grades: ChoiceOption[];
    min_tenure_months: number;
    sbc_integration: string;
    sbc_integration_label: string;
    sbc_cap: string;
    in_payroll_tax: boolean;
    in_ptu_base: boolean;
    in_seniority_base: boolean;
    income_tax_exempt: boolean;
    cap: string;
    archived: boolean;
    /** The terms in force today; none before the first vigencia. */
    current: { value_text: string; effective_from: string } | null;
    warning: string | null;
    terms: BenefitTerm[];
};

/** What the benefit form chooses from: the fixed catalogs and the route that searches the pay scale's categories. */
export type BenefitChoices = {
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    groups: ChoiceOption[];
    sbcIntegrations: ChoiceOption[];
    gradesUrl: string;
};

/** The IMSS work risk premium in force today, as a percent. */
export type RiskPremium = { rate: string; effective_from: string };

/** A daily salary as the server sends it: the number to edit, the amount written and the date it starts. */
export type SalaryEntry = {
    daily_salary: string;
    daily_salary_text: string;
    /** `AAAA-MM-DD`. */
    effective_from: string;
};

/** A category and level of the tabulator with its salaries, latest vigencia first, and the one in force today. */
export type TabulatorRow = {
    id: string;
    category: string;
    level: string;
    current: SalaryEntry | null;
    salaries: (SalaryEntry & { id: string })[];
};

/** The problems that kept a salaries CSV from being imported, each row numbered as the spreadsheet numbers it. */
export type SalaryImportSummary = {
    status: 'invalid' | 'imported';
    rows: number;
    errors: { row: number; messages: string[] }[];
};

/** A person's individual salary: the note says why it is not their category's. */
export type OverrideSalary = SalaryEntry & { id: string; note: string | null };

/** A person with an individual salary, told apart by the company's own ID. `active` is false when the roster no longer has them to cost. */
export type OverrideRow = {
    employee_number: string;
    /** "Operador · A", when the person is in the roster and can be costed. */
    grade: string | null;
    active: boolean;
    current: OverrideSalary | null;
    salaries: OverrideSalary[];
};

/** A scenario as the «Escenarios» list shows it: what it proposes and what it costs in its first year. */
export type ScenarioRow = {
    id: string;
    name: string;
    /** `AAAA-MM-DD`. */
    effective_on: string;
    horizon_years: number;
    retroactive_months: number;
    /** A percent as typed: "5.75". */
    unionized_raise: string;
    /** Null when the trusted staff copy the unionized raise. */
    trusted_raise: string | null;
    changed_benefits: number;
    people: number;
    /** The change over the 12 months plus the retroactive months, written as money. */
    first_year_text: string;
    /** What the change costs per year from the effective date, written as money. */
    recurring_text: string;
    archived: boolean;
};

/** A scenario as its editor types it. */
export type ScenarioDetail = {
    id: string;
    name: string;
    effective_on: string;
    retroactive_months: number;
    horizon_years: number;
    unionized_raise: string;
    /** Empty when the trusted staff copy the unionized raise. */
    trusted_raise: string;
    /** A state code, or empty for none. */
    default_state: string;
    archived: boolean;
    term_changes: TermChangeForm[];
};

/** One assumption of the long-term projection with where its value comes from. */
export type AssumptionField = {
    key: string;
    label: string;
    /** A percent as typed. */
    value: string;
    source: string;
    /** `AAAA-MM-DD`. */
    set_on: string;
};
