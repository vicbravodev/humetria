import { useState } from 'react';
import { yearStart } from '@/lib/compensation/benefits';

/** The first day of this year, read once when the form opens: the usual start of new contract terms. */
export function useYearStart(): string {
    const [start] = useState(() => yearStart(new Date()));

    return start;
}
