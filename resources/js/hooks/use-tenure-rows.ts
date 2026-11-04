import { useState } from 'react';
import type { TenureFormRow } from '@/lib/compensation/benefits';
import {
    addTenureRow,
    removeTenureRow,
    setTenureRow,
} from '@/lib/compensation/benefits';

/** The rows of a seniority table while they are typed: add one, change a field of one, remove one. */
export function useTenureRows(initial: TenureFormRow[]) {
    const [rows, setRows] = useState(initial);

    return {
        rows,
        add: () => setRows(addTenureRow),
        remove: (index: number) =>
            setRows((current) => removeTenureRow(current, index)),
        change: (index: number, patch: Partial<TenureFormRow>) =>
            setRows((current) => setTenureRow(current, index, patch)),
    };
}
