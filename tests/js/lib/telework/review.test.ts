import { describe, expect, it } from 'vitest';
import {
    answerLabel,
    authorizationState,
    AUTHORIZATION_BADGES,
    decisionNeeds,
    DECISIONS,
    evidenceKindLabel,
    openEvidence,
    statusTone,
} from '@/lib/telework/review';
import type {
    ReviewAuthorization,
    ReviewEvidence,
    WorkplaceStatusValue,
} from '@/types';

describe('statusTone', () => {
    it.each<[WorkplaceStatusValue, string]>([
        ['valid', 'success'],
        ['valid_with_plan', 'warning'],
        ['not_valid', 'destructive'],
        ['retired', 'secondary'],
        ['pending', 'default'],
    ])('paints %s as %s', (status, tone) => {
        expect(statusTone(status)).toBe(tone);
    });
});

describe('decisionNeeds', () => {
    it('asks for the plan only when validating with a plan', () => {
        expect(decisionNeeds('valid_with_plan')).toEqual({
            plan: true,
            notes: false,
        });
    });

    it('asks for notes only when not validating', () => {
        expect(decisionNeeds('not_valid')).toEqual({
            plan: false,
            notes: true,
        });
    });

    it('asks for nothing more when validating', () => {
        expect(decisionNeeds('valid')).toEqual({ plan: false, notes: false });
    });

    it('offers the three decisions, each with its hint', () => {
        expect(DECISIONS.map((decision) => decision.value)).toEqual([
            'valid',
            'valid_with_plan',
            'not_valid',
        ]);
        expect(DECISIONS.every((decision) => decision.hint !== '')).toBe(true);
    });
});

describe('answerLabel', () => {
    it.each<[string | null, string]>([
        ['yes', 'Sí'],
        ['no', 'No'],
        ['not_applicable', 'No aplica'],
        [null, 'Sin respuesta'],
    ])('names %s', (answer, label) => {
        expect(answerLabel(answer)).toBe(label);
    });
});

describe('evidence', () => {
    const request = (id: string, fulfilled: boolean): ReviewEvidence => ({
        id,
        kind: 'photos',
        note: '',
        requestedOn: '2026-01-01',
        fulfilled,
        photos: [],
    });

    it('keeps only what is still open', () => {
        expect(
            openEvidence([request('a', true), request('b', false)]).map(
                (item) => item.id,
            ),
        ).toEqual(['b']);
    });

    it('names the kind', () => {
        expect(evidenceKindLabel('photos')).toBe('Fotos');
        expect(evidenceKindLabel('remote_check')).toBe(
            'Comprobación a distancia',
        );
    });
});

describe('authorizationState', () => {
    const base: ReviewAuthorization = {
        id: 'a',
        requestedOn: '2026-01-01',
        authorized: false,
        cancelled: false,
        viaPortal: false,
        hasDocument: false,
        used: false,
    };

    it.each<[Partial<ReviewAuthorization>, string]>([
        [{}, 'waiting'],
        [{ authorized: true }, 'given'],
        [{ authorized: true, used: true }, 'used'],
        [{ cancelled: true, used: true }, 'cancelled'],
    ])('reads %j as %s', (change, state) => {
        const found = authorizationState({ ...base, ...change });

        expect(found).toBe(state);
        expect(AUTHORIZATION_BADGES[found].label).not.toBe('');
    });
});
