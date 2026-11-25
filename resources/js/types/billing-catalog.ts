/** A plan of the catalog in force, as the console shows it: its price (a rate per person with a minimum, or a union's flat fee), what it includes and who is on it. */
export type CatalogPlan = {
    key: string;
    label: string;
    rate: string | null;
    flat: string | null;
    minimum: string | null;
    included_centers: number;
    /** The keys of the modules it enables. */
    modules: string[];
    /** Companies on it, billed (active, past due or cancelling). */
    companies: number;
    /** Of them, the ones that renew in the next 90 days (a cancelling one ends instead). */
    renewing: number;
};

/** The catalog in force, read from the code with its version. */
export type CatalogView = {
    version: string;
    effective_on: string;
    vat_percent: number;
    /** Every module any plan has, in the catalog's order. */
    modules: string[];
    plans: CatalogPlan[];
};

/** A price agreed by hand for a company, still in force. */
export type NegotiatedPriceRow = {
    company: string;
    plan: string;
    price: string;
    /** Null when it has no end. */
    valid_until: string | null;
};
