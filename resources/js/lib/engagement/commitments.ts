import type { CommitmentRow, CommitmentStatus } from '@/types';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'success'
    | 'warning'
    | 'destructive';

/** The tone of a commitment's state: one that is late is a problem whatever else it is. */
export function statusVariant(
    status: CommitmentStatus,
    overdue: boolean,
): BadgeVariant {
    if (overdue) {
        return 'destructive';
    }

    switch (status) {
        case 'open':
            return 'warning';
        case 'in_progress':
            return 'default';
        case 'done':
            return 'success';
        case 'dropped':
            return 'secondary';
    }
}

const ORDER: CommitmentStatus[] = ['open', 'in_progress', 'done', 'dropped'];

/** The states a commitment can move to: every other one. */
export function nextStatuses(status: CommitmentStatus): CommitmentStatus[] {
    return ORDER.filter((candidate) => candidate !== status);
}

/** What a group is as one value of a choice field: the company alone, anything else with its type. */
export function groupChoice(group: {
    type: CommitmentRow['group_type'];
    id: string | null;
}): string {
    return group.id === null ? group.type : `${group.type}:${group.id}`;
}
