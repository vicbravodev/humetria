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
};

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
