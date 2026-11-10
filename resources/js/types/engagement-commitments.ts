import type { Paginated } from './demos';

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
    status: CommitmentStatus | null;
    statuses: { value: CommitmentStatus; label: string }[];
    drivers: CommitmentOption[];
    commitments: Paginated<CommitmentRow>;
    counts: Record<'all' | CommitmentStatus, number>;
    groups: CommitmentGroupOption[];
    suggestions: CommitmentSuggestion[];
};
