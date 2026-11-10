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
