import type { OrgNode } from '@/types';

const TEXTS = ['id', 'name', 'job_title', 'area', 'work_center'];
const FLAGS = ['active', 'matches', 'manager_inactive'];
const COUNTS = ['direct_reports', 'total_reports'];

function isFlag(value: unknown): value is OrgNode['flag'] {
    return (
        value === null ||
        (typeof value === 'object' &&
            'value' in value &&
            (value.value === 'few' || value.value === 'many') &&
            'label' in value &&
            typeof value.label === 'string')
    );
}

function isOrgNode(value: unknown): value is OrgNode {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const fields = new Map<string, unknown>(Object.entries(value));
    const all = (keys: string[], type: string) =>
        keys.every((key) => typeof fields.get(key) === type);

    return (
        all(TEXTS, 'string') &&
        all(FLAGS, 'boolean') &&
        all(COUNTS, 'number') &&
        fields.has('flag') &&
        isFlag(fields.get('flag'))
    );
}

/** A page of reports as the server answers it: only what has the shape of a person is kept; null when it is no page. */
export function reportsPage(
    body: unknown,
): { nodes: OrgNode[]; nextPage: number | null } | null {
    if (
        typeof body !== 'object' ||
        body === null ||
        !('data' in body) ||
        !Array.isArray(body.data) ||
        !('current_page' in body) ||
        typeof body.current_page !== 'number' ||
        !('last_page' in body) ||
        typeof body.last_page !== 'number'
    ) {
        return null;
    }

    return {
        nodes: body.data.filter(isOrgNode),
        nextPage:
            body.current_page < body.last_page ? body.current_page + 1 : null,
    };
}
