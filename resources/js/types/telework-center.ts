import type { TeleworkerStatus, WorkplaceStatusValue } from './telework';

export type RowStateValue = 'done' | 'missing' | 'partial' | 'not_applicable';

/** One row of the inspection table 10.3 for a center, with where to fix it when the person may open that. */
export type ComplianceRow = {
    numeral: string;
    label: string;
    state: RowStateValue;
    stateLabel: string;
    detail: string | null;
    href: string | null;
    action: string | null;
};

/** The rows done over the rows that apply to the center. */
export type CenterProgress = { done: number; applicable: number };

/** A center on the NOM-037 list. */
export type CenterCard = CenterProgress & {
    id: string;
    name: string;
    activity: string;
    teleworkers: number;
    aspirants: number;
    pendingReviews: number;
};

export type CenterNextStepData = {
    kind: 'committee' | 'checklist';
    title: string;
    body: string;
    cta: string;
    href: string | null;
};

/** A view of the center with how many it holds; `active` is the page itself. */
export type CenterTab = {
    key: string;
    label: string;
    count: number;
    href: string;
    active: boolean;
};

export type CenterWorkplace = {
    id: string;
    status: WorkplaceStatusValue;
    statusLabel: string;
};

/** A person of the roster. The three personal fields come only for whoever manages NOM-037. */
export type CenterPerson = {
    id: string;
    name: string;
    jobTitle: string;
    percentage: number;
    status: TeleworkerStatus;
    workplaces: CenterWorkplace[];
    href: string | null;
    maritalStatus?: string | null;
    contactPhone?: string | null;
    homeAddress?: string | null;
};
