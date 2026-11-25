/** How old a balance is: not yet due, or past due 1–30, 31–60 or more than 60 days. */
export type AgingBucket = 'current' | '1_30' | '31_60' | 'over_60';

/** The age of an account's oldest open statement: `days` past due, or left to pay while it is current. */
export type AccountAging = { bucket: AgingBucket; days: number };

/** What all the accounts owe and how old it is, what came in this month and how many plans renew in 30 days. */
export type ReceivablePortfolio = {
    outstanding: string;
    overdue: string;
    open_statements: number;
    overdue_accounts: number;
    /** Each age with what it holds and its share of the whole, in whole percent. */
    aging: { key: AgingBucket; amount: string; share: number }[];
    collected: string;
    /** The month `collected` is of, by name: "noviembre". */
    month: string;
    renewing: number;
};
