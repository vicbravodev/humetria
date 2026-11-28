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
    /** The months of the statements it settled, oldest first. */
    applied_to: string[];
};

/** What can be done with a company an account pays, each in its dialog: its plan, a statement, an extra charge, a transfer. */
export type CompanyDialogKind = 'plan' | 'statement' | 'charge' | 'transfer';

/** The account's year so far: what it billed and paid, and on average how many days after they were due it paid (negative: before). */
export type AccountYear = {
    year: number;
    billed: string;
    billed_statements: number;
    paid: string;
    payments: number;
    average_days: number | null;
};
