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

/** The next state of the cycle, the one button of a card: in progress after open, done after in progress. */
export function primaryStep(status: CommitmentStatus): CommitmentStatus | null {
    switch (status) {
        case 'open':
            return 'in_progress';
        case 'in_progress':
            return 'done';
        default:
            return null;
    }
}

/** Every other state a commitment can move to, under the menu of its card. */
export function menuSteps(status: CommitmentStatus): CommitmentStatus[] {
    return nextStatuses(status).filter(
        (candidate) => candidate !== primaryStep(status),
    );
}

/** What a group is as one value of a choice field: the company alone, anything else with its type. */
export function groupChoice(group: {
    type: CommitmentRow['group_type'];
    id: string | null;
}): string {
    return group.id === null ? group.type : `${group.type}:${group.id}`;
}
