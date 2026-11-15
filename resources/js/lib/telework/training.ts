import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type {
    BadgeVariant,
    TrainingPerson,
    TrainingStandingValue,
} from '@/types';

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

/**
 * What the row of a person says about their training: when they last had one and until when the next is due. Whoever never
 * trained and is still within their first year is not «Al corriente»: they have a deadline.
 */
export function trainingLines(
    person: Pick<
        TrainingPerson,
        'lastTrainingOn' | 'dueOn' | 'status' | 'statusLabel'
    >,
): { last: string; due: string; label: string } {
    const due = describeDate(person.dueOn) ?? person.dueOn;

    if (person.lastTrainingOn !== null) {
        return {
            last: `Última: ${describeDate(person.lastTrainingOn) ?? person.lastTrainingOn}`,
            due: `Vence el ${due}`,
            label: person.statusLabel,
        };
    }

    if (person.status === 'overdue') {
        return {
            last: 'Sin capacitación',
            due: `Venció el ${due}`,
            label: person.statusLabel,
        };
    }

    return {
        last: 'Aún sin capacitación',
        due: `tiene hasta el ${due}`,
        label: person.status === 'ok' ? 'En plazo' : person.statusLabel,
    };
}
