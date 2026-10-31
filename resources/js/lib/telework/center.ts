import { MARITAL_STATUSES } from '@/lib/telework/roster';
import type { BadgeTone } from '@/lib/telework/review';
import type { CenterProgress, RowStateValue } from '@/types';

/** The badge of a row of table 10.3: done, partial, missing, or not applicable. */
export function rowTone(state: RowStateValue): BadgeTone {
    switch (state) {
        case 'done':
            return 'success';
        case 'partial':
            return 'warning';
        case 'missing':
            return 'destructive';
        case 'not_applicable':
            return 'secondary';
    }
}

/** What the gauge counts, for screen readers and the caption: "5 de 16 renglones". */
export function progressText({ done, applicable }: CenterProgress): string {
    return `${done} de ${applicable} ${applicable === 1 ? 'renglón' : 'renglones'}`;
}

/** The people of a center in one line: "3 personas · 1 aspirante". */
export function headcountText(teleworkers: number, aspirants: number): string {
    const people = teleworkers === 1 ? '1 persona' : `${teleworkers} personas`;

    if (aspirants === 0) {
        return people;
    }

    return `${people} · ${aspirants === 1 ? '1 aspirante' : `${aspirants} aspirantes`}`;
}

/** "Sin listas por revisar", "1 lista por revisar", "4 listas por revisar". */
export function pendingText(count: number): string {
    if (count === 0) {
        return 'Sin listas por revisar';
    }

    return count === 1 ? '1 lista por revisar' : `${count} listas por revisar`;
}

/** The estado civil of a person, as 5.1 words it; a dash when none is on file. */
export function maritalText(status: string | null | undefined): string {
    return (
        MARITAL_STATUSES.find((option) => option.value === status)?.label ?? '—'
    );
}
