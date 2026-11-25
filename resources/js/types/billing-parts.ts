import type { Quote } from '@/types/billing';

/** The tools (by name) a plan switches on today and the ones it takes away, from `removedOn`. */
export type ModulesChange = {
    added: string[];
    removed: string[];
    removedOn: string | null;
};

/** One entry of a SAT catalog: its code (what is stored) and its label with the name. */
export type SatOption = { value: string; label: string };

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
