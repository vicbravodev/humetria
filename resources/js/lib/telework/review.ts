import { CalendarClock, MapPin, User } from 'lucide-react';
import { describeDate } from '@/lib/calendar-date';
import { plural } from '@/lib/plural';
import type {
    BadgeVariant,
    PageFact,
    ReviewDetail,
    ReviewAuthorization,
    ReviewDecisionValue,
    ReviewEvidence,
    WorkplaceStatusValue,
} from '@/types';

/** The badge of a workplace's status: validated is success, a plan needs a look, not validated is blocked. */
export function statusTone(status: WorkplaceStatusValue): BadgeVariant {
    switch (status) {
        case 'valid':
            return 'success';
        case 'valid_with_plan':
            return 'warning';
        case 'not_valid':
            return 'destructive';
        case 'retired':
            return 'secondary';
        case 'pending':
            return 'default';
    }
}

export const DECISIONS: {
    value: ReviewDecisionValue;
    label: string;
    hint: string;
}[] = [
    {
        value: 'valid',
        label: 'Validado',
        hint: 'El lugar cumple; vale hasta la siguiente revisión.',
    },
    {
        value: 'valid_with_plan',
        label: 'Validado con plan de acción',
        hint: 'Cumple, con mejoras por hacer en una fecha compromiso.',
    },
    {
        value: 'not_valid',
        label: 'No validado',
        hint: 'No cumple todavía; las notas explican por qué.',
    },
];

/**
 * What a decision still needs besides the choice itself. Not validating always needs notes, and so does validating a list
 * with unfavorable answers (the Comisión's observation, C-12).
 */
export function decisionNeeds(
    decision: ReviewDecisionValue,
    unfavorable = 0,
): {
    plan: boolean;
    notes: boolean;
} {
    return {
        plan: decision === 'valid_with_plan',
        notes: decision === 'not_valid' || unfavorable > 0,
    };
}

/** The answer as the person gave it, in the Comisión's words. */
export function answerLabel(answer: string | null): string {
    switch (answer) {
        case 'yes':
            return 'Sí';
        case 'no':
            return 'No';
        case 'not_applicable':
            return 'No aplica';
        default:
            return 'Sin respuesta';
    }
}

/** The evidence still open, oldest request last as the server sorts it. */
export function openEvidence(evidence: ReviewEvidence[]): ReviewEvidence[] {
    return evidence.filter((request) => !request.fulfilled);
}

export function evidenceKindLabel(kind: ReviewEvidence['kind']): string {
    return kind === 'photos' ? 'Fotos' : 'Comprobación a distancia';
}

export type AuthorizationState = 'cancelled' | 'used' | 'given' | 'waiting';

/** Where a visit request stands: cancelled and used are over; given can be used; waiting needs the person. */
export function authorizationState(
    authorization: ReviewAuthorization,
): AuthorizationState {
    if (authorization.cancelled) {
        return 'cancelled';
    }

    if (authorization.used) {
        return 'used';
    }

    return authorization.authorized ? 'given' : 'waiting';
}

export const AUTHORIZATION_BADGES: Record<
    AuthorizationState,
    { label: string; tone: BadgeVariant }
> = {
    cancelled: { label: 'Cancelada', tone: 'secondary' },
    used: { label: 'Usada', tone: 'secondary' },
    given: { label: 'Autorizada', tone: 'success' },
    waiting: { label: 'Esperando a la persona', tone: 'warning' },
};

/** Under a review's title: whose workplace it is, where, and until when it stays valid. */
export function reviewFacts({
    person,
    workplace,
}: Pick<ReviewDetail, 'person' | 'workplace'>): PageFact[] {
    const expires =
        workplace.expiresOn === null
            ? undefined
            : describeDate(workplace.expiresOn);

    return [
        { icon: User, text: `${person.name} · ${person.jobTitle}` },
        { icon: MapPin, text: workplace.address },
        ...(expires === undefined
            ? []
            : [{ icon: CalendarClock, text: `Vigente hasta ${expires}` }]),
    ];
}

/** How many checklists wait for the Comisión, the oldest first. */
export function pendingReviewsText(total: number): string {
    return total === 0
        ? 'No hay listas esperando a la Comisión.'
        : `${total} ${plural(total, 'lista espera', 'listas esperan')} a la Comisión, la más antigua primero.`;
}
