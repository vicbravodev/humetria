import type { TenantRow } from '@/types/tenancy';

export type AccountKind = 'direct' | 'consultant';

/** The kinds the console's tabs choose among: one, or all. */
export type AccountView = AccountKind | 'all';

export type AccountFilters = { q: string; kind: AccountView };

/** An account of the console's list: who pays, how to reach them and how many companies they pay now. */
export type AccountRow = {
    id: string;
    kind: AccountKind;
    kind_label: string;
    legal_name: string;
    rfc: string;
    contact_name: string;
    contact_email: string;
    companies: number;
};

/** An account with the fiscal data a CFDI will need and the contract notes. */
export type AccountDetail = Omit<AccountRow, 'companies'> & {
    tax_regime: string | null;
    postal_code: string | null;
    cfdi_use: string | null;
    contact_phone: string | null;
    notes: string | null;
};

/** A company an account pays, as the account's page lists it; `subscription` is null while it has no plan. */
export type AccountCompanyRow = {
    company_id: string;
    trade_name: string;
    legal_name: string;
    rfc: string;
    slug: string;
    status: TenantRow['status'];
    status_label: string;
    work_centers: number;
    /** The active people the company has now, against the seats it pays. */
    people: number;
    /** The day the account started paying it, written for display ("1 dic 2026"). */
    linked_since: string;
    subscription: SubscriptionView | null;
};

export type Plan = 'jornada' | 'cumplimiento' | 'integral';

export type PriceProfile =
    | 'direct'
    | 'channel_compliance'
    | 'channel_integral'
    | 'negotiated';

export type SubscriptionStatus =
    | 'trial'
    | 'active'
    | 'past_due'
    | 'cancel_scheduled'
    | 'ended';

export type QuoteLine = { label: string; amount: string; explanation: string };

/** What a plan costs for a period, every amount already written as pesos by the server; null amounts mean a quotation. */
export type Quote = {
    requires_quotation: boolean;
    explanation: string;
    lines: QuoteLine[];
    subtotal: string | null;
    subtotal_cents: number | null;
    vat: string | null;
    total: string | null;
};

/** Why a consultant's Integral discount applies, or what is missing for it. */
export type DiscountNote = { applies: boolean; reason: string };

/** What a company contracted, as the account's page lists it and its form edits it. */
export type SubscriptionView = {
    plan: Plan;
    plan_label: string;
    profile: PriceProfile;
    profile_label: string;
    status: SubscriptionStatus;
    status_label: string;
    seats: number;
    centers: number;
    /** The first day, Y-m-d, for the form. */
    starts_on: string;
    /** The next cut, written for display. */
    renews_on: string;
    catalog_version: string;
    /** Pesos as a field holds them ("8149.50"); empty without a negotiated price. */
    negotiated_price: string;
    negotiated_valid_until: string;
    quote: Quote;
};

/** The server's answer while a plan is being typed: its price, what the company pays now and why a discount applies or not. */
export type SubscriptionPreview = {
    quote: Quote;
    current: Quote | null;
    first_period: Quote | null;
    eligibility: DiscountNote | null;
};

/** What an account adds up to across its companies. */
export type AccountSummary = {
    companies: number;
    with_plan: number;
    without_plan: number;
    subtotal: string | null;
    vat: string | null;
    total: string | null;
    requiring_quotation: number;
    next_renewal: string | null;
    discount: DiscountNote | null;
};
