/** A row of table 10.3 as an inspector would ask for it (board V3-Inspeccion): in plain words, with its way to fix or see it. */
export type InspectionItem = {
    numeral: string;
    title: string;
    detail: string | null;
    state: 'done' | 'partial' | 'missing';
    /** Null for whoever may not open it (the viewer). */
    href: string | null;
    action: string | null;
};
