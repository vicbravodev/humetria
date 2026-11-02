import { countOf } from '@/lib/plural';
import type { BadgeVariant, TrainingStandingValue } from '@/types';

/** The badge of where a person stands on their yearly training: up to date, soon due, or past due. */
export function standingTone(status: TrainingStandingValue): BadgeVariant {
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
    return countOf(count, 'persona', 'personas');
}
