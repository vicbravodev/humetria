import type { ChoiceOption } from '@/types/organization';

export type {
    BenefitCounts,
    BenefitFilters,
    TenureFormRow,
} from '@/lib/compensation/benefits';

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
