import type { ModulesChange } from '@/types/billing-parts';
import type { TenantRow } from '@/types/tenancy';

export type AccountKind = 'direct' | 'consultant';

/** The kinds the console's tabs choose among: one, or all. */
/** A kind of account, all of them, or the queue of those with a balance past due. */
export type AccountView = AccountKind | 'all' | 'overdue';

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
    /** What it owes past due, written as pesos; null when nothing is. */
    overdue: string | null;
};

/** An account with the fiscal data a CFDI will need and the contract notes. */
export type AccountDetail = Omit<AccountRow, 'companies' | 'overdue'> & {
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
    /** The account that paid it before, when it came by a transfer. */
    previous_payer: string | null;
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
    /** What the company may really have: never fewer than the plan includes. */
    allowed_centers: number;
    /** The first day, Y-m-d, for the form. */
    starts_on: string;
    /** The next cut, written for display. */
    renews_on: string;
    catalog_version: string;
    /** Pesos as a field holds them ("8149.50"); empty without a negotiated price. */
    negotiated_price: string;
    negotiated_valid_until: string;
    quote: Quote;
    /** What waits for the next cut (a reduction) and from when; null when nothing does. */
    scheduled: { effective_on: string; summary: string } | null;
};

/** The server's answer while a plan is being typed: its price, what the company pays now and why a discount applies or not. */
export type SubscriptionPreview = {
    quote: Quote;
    current: Quote | null;
    first_period: Quote | null;
    eligibility: DiscountNote | null;
    /** When a reduction in the plan being typed takes effect (the next cut); null when everything applies at once. */
    scheduled: { effective_on: string } | null;
    modules: ModulesChange;
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

/** An extra charge of a company the account pays, as the account's page lists it. */
export type AccountChargeRow = {
    id: string;
    company_id: string;
    trade_name: string;
    kind: 'recurring' | 'one_time';
    kind_label: string;
    description: string;
    scope: string;
    /** Pesos, written for display ("$1,500.50"). */
    amount: string;
    charge_on: string;
    /** The last day of a recurring charge that ends; null otherwise. */
    ends_on: string | null;
    approved_by: string;
    /** A one-time charge a statement already billed: it stays. */
    billed: boolean;
};

/** Where a statement stands: a draft, or an issued one by how much of it has been paid. */
export type StatementState =
    | 'draft'
    | 'open'
    | 'partially_paid'
    | 'paid'
    | 'credit';

/** A statement of account as the account's page lists it. A draft has nothing paid and no balance yet. */
export type StatementRow = {
    id: string;
    company_id: string;
    trade_name: string;
    period: string;
    is_adjustment: boolean;
    total: string;
    paid: string | null;
    balance: string | null;
    state: StatementState;
    state_label: string;
};

/** A payment of the account: what came in, how much of it settled statements, and why it was reversed if it was. */
export type PaymentRow = {
    id: string;
    reference: string;
    paid_on: string;
    amount: string;
    applied: string;
    unapplied: string;
    note: string | null;
    recorded_by: string;
    reversed: boolean;
    reversal_reason: string | null;
    can_apply: boolean;
};

/** What the account owes across its issued statements. */
export type Receivable = {
    outstanding: string;
    open_statements: number;
};

/** The statement page's document: the snapshot the amounts came from and who made and issued it. */
export type StatementDetail = {
    id: string;
    account_id: string;
    account_name: string;
    account_rfc: string;
    company_id: string;
    trade_name: string;
    legal_name: string;
    company_rfc: string;
    period: string;
    period_range: string;
    status: 'draft' | 'issued';
    state: StatementState;
    state_label: string;
    is_adjustment: boolean;
    adjusts_id: string | null;
    reason: string | null;
    currency: string;
    catalog_version: string;
    vat_percent: number;
    subtotal: string;
    vat: string;
    total: string;
    paid: string | null;
    balance: string | null;
    created_by: string;
    issued_by: string | null;
    issued_at: string | null;
};

/** One line of a statement, with how its amount was reached. */
export type StatementLine = {
    label: string;
    amount: string;
    explanation: string;
    code: string;
};

/** A payment applied to a statement. */
export type AppliedPayment = {
    payment_id: string;
    reference: string;
    paid_on: string;
    amount: string;
    reversed: boolean;
};

/** A statement that corrects the one on the page. */
export type StatementAdjustment = {
    id: string;
    total: string;
    reason: string;
};

/** The lines of a month before its draft is made. */
export type StatementPreview = {
    period: string;
    lines: QuoteLine[];
    subtotal: string;
    vat: string;
    total: string;
};

/** An issued statement with a balance, which a payment can be applied to. */
export type OpenStatement = {
    id: string;
    label: string;
    balance: string;
    balance_input: string;
};

/** What moving a company between payers would do for an account that is a consultant's: its Integral discount before and after. */
export type TransferEffect = {
    role: 'from' | 'to';
    account: string;
    before: number;
    after: number;
    effect: 'loses' | 'gains' | 'same';
    text: string;
};

/** The server's answer while a move is being typed: the new price, how the pricing changes and who loses or gains the discount. */
export type TransferPreview = {
    from: { id: string; name: string };
    to: { id: string; name: string } | null;
    effective_on: string;
    current: Quote | null;
    quote: Quote | null;
    profile: { before: string; after: string } | null;
    effects: TransferEffect[];
    access: string;
};

/** A company that left the account or came to it, with the cut, the reason and who decided it. */
export type TransferRow = {
    id: string;
    company: string;
    direction: 'out' | 'in';
    other: string;
    effective_on: string;
    reason: string;
    by: string;
    profile: string | null;
};
