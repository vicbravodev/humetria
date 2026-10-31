import type { BadgeTone } from '@/lib/telework/review';
import type { TrainingStandingValue } from '@/types';

/** The badge of where a person stands on their yearly training: up to date, soon due, or past due. */
export function standingTone(status: TrainingStandingValue): BadgeTone {
    switch (status) {
        case 'ok':
            return 'success';
        case 'due_soon':
            return 'warning';
        case 'overdue':
            return 'destructive';
    }
}

/** "3 personas" / "1 persona", for the attendees of a training. */
export function attendeesText(count: number): string {
    return count === 1 ? '1 persona' : `${count} personas`;
}
