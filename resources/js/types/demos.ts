export type DemoRequestRow = {
    id: string;
    name: string;
    email: string;
    whatsapp: string;
    company: string;
    profile: string;
    workforce: string;
    tools: string[];
    message: string | null;
    received_at: string;
    status: DemoStatus;
    status_label: string;
    /** The team's own note; never shown to the person who asked. */
    note: string | null;
    /** The company it became, once it has. */
    tenant_id: string | null;
};

export type DemoStatus = 'new' | 'contacted' | 'converted' | 'discarded';

export type DemoView = 'all' | DemoStatus;

export type DemoFilters = { status: DemoView };

/** The part of a Laravel LengthAwarePaginator the console reads. */
export type Paginated<T> = {
    data: T[];
    total: number;
    /** The first and last row on this page, 1-based; null on an empty page. */
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** The demo request a company is about to be created from, to fill the dialog with. */
export type TenantPrefill = {
    demo_request_id: string;
    company: string;
    contact: string;
    email: string;
};
