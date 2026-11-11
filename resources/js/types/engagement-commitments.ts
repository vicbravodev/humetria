import type { Paginated } from './demos';
import type { EngagementLoopAction } from './engagement-results';

export type CommitmentStatus = 'open' | 'in_progress' | 'done' | 'dropped';

export type CommitmentGroupType =
    | 'company'
    | 'work_center'
    | 'area'
    | 'manager';

export type CommitmentRow = {
    id: string;
    group_type: CommitmentGroupType;
    group_id: string | null;
    group_name: string;
    driver: string | null;
    driver_label: string | null;
    heard: string;
    will_do: string;
    owner_name: string;
    /** Y-m-d */
    due_on: string;
    status: CommitmentStatus;
    status_label: string;
    overdue: boolean;
};

/** A group the results show, where a commitment may be written. */
export type CommitmentGroupOption = {
    type: CommitmentGroupType;
    id: string | null;
    name: string;
    /** It already has its three commitments. */
    full: boolean;
};

export type CommitmentSuggestion = {
    driver: string;
    label: string;
    levelLabel: string;
    actions: string[];
};

export type CommitmentOption = { value: string; label: string };

export type EngagementCommitments = {
    survey: { id: string; name: string; status: string };
    available: boolean;
    canManage: boolean;
    /** The commitments already reached the portal of the people. */
    published: boolean;
    status: CommitmentStatus | null;
    statuses: { value: CommitmentStatus; label: string }[];
    drivers: CommitmentOption[];
    commitments: Paginated<CommitmentRow>;
    counts: Record<'all' | CommitmentStatus, number>;
    groups: CommitmentGroupOption[];
    suggestions: CommitmentSuggestion[];
    loopAction: EngagementLoopAction | null;
};

/** A commitment as the portal shows it: no id of a group, only what the person reads. */
export type PortalCommitment = {
    id: string;
    group_name: string;
    driver_label: string | null;
    heard: string;
    will_do: string;
    owner_name: string;
    /** Y-m-d */
    due_on: string;
    status: CommitmentStatus;
    status_label: string;
    overdue: boolean;
};

export type PortalClimate = {
    token: string;
    company: string;
    name: string;
    survey: { name: string };
    /** Null when the company is too small to say how many answered. */
    participation: { percent: number } | null;
    index: number | null;
    commitments: Paginated<PortalCommitment>;
};
