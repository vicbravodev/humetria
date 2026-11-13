import { describe, expect, it } from 'vitest';
import {
    feedbackProblem,
    isSequence,
    punchProblem,
} from '@/lib/attendance/punch-problem';

const labels = (
    reason: string,
    location?: Parameters<typeof punchProblem>[1],
) => punchProblem(reason, location)?.actions.map(({ kind }) => kind);

describe('punchProblem', () => {
    it('says what happened and what to do, for a mark outside the zone', () => {
        const problem = punchProblem('outside_zone');

        expect(problem).toMatchObject({
            kind: 'zone',
            tone: 'blocked',
            title: 'Está fuera de la zona',
        });
        expect(problem?.text).toContain('Acérquese');
        expect(labels('outside_zone')).toEqual(['retry', 'correction']);
    });

    it('asks for the precise location when the reading is not precise enough', () => {
        expect(punchProblem('accuracy_too_low')).toMatchObject({
            kind: 'zone',
            title: 'Su ubicación no es lo bastante precisa',
        });
        expect(punchProblem('accuracy_too_low')?.text).toContain('precisa');
    });

    it('asks to allow the location when the phone did not let the page read it, and tells how', () => {
        expect(punchProblem('no_location', 'denied')).toMatchObject({
            kind: 'location',
            title: 'No tenemos su ubicación',
        });
        expect(punchProblem('no_location', 'denied')?.text).toContain(
            'Permita la ubicación para este sitio',
        );
        expect(labels('no_location', 'denied')).toEqual([
            'retry',
            'howto',
            'correction',
        ]);
        expect(labels('no_location')).toEqual(['retry', 'howto', 'correction']);
        expect(punchProblem('no_location', 'denied')?.actions[0]?.label).toBe(
            'Permitir ubicación',
        );
    });

    it('says the phone could not find the position when it timed out or had none, without blaming the permission', () => {
        for (const location of ['timeout', 'unavailable'] as const) {
            const problem = punchProblem('no_location', location);

            expect(problem?.title).toBe('No encontramos su ubicación');
            expect(problem?.text).toContain('lugar abierto');
            expect(labels('no_location', location)).toEqual([
                'retry',
                'correction',
            ]);
        }
    });

    it('never offers to mark without a position: the company asked for it', () => {
        const text = JSON.stringify(punchProblem('no_location', 'denied'));

        expect(text).not.toContain('sin ubicación');
    });

    it('asks to scan the code of the screen again, without blame', () => {
        expect(punchProblem('qr_invalid')).toMatchObject({
            kind: 'qr',
            title: 'Ese código ya venció',
        });
        expect(punchProblem('qr_invalid')?.text).toContain('30 segundos');
        expect(punchProblem('qr_required')).toMatchObject({
            kind: 'qr',
            title: 'Falta escanear el código',
        });
        expect(labels('qr_required')).toEqual(['dismiss', 'correction']);
    });

    it('tells a person of a center that cannot mark yet to warn RH, never to scan a screen', () => {
        const problem = punchProblem('center_not_ready');

        expect(problem).toMatchObject({
            kind: 'zone',
            tone: 'warning',
            title: 'Su centro aún no está listo para marcar',
            text: 'Avise a Recursos Humanos; mientras tanto puede pedir una corrección.',
        });
        expect(JSON.stringify(problem)).not.toContain('pantalla');
        expect(labels('center_not_ready')).toEqual(['dismiss', 'correction']);
    });

    it('sends a phone that is not linked to the link of RH', () => {
        expect(punchProblem('not_verified')).toMatchObject({
            kind: 'device',
            title: 'Este teléfono no está vinculado',
        });
        expect(labels('not_verified')).toEqual(['copy-hr', 'dismiss']);
    });

    it.each([
        ['cancelled', 'No se confirmó que es usted', ['retry']],
        ['throttled', 'Hubo demasiados intentos', ['dismiss']],
        ['unsupported', 'Este navegador no puede marcar', ['dismiss']],
        ['failed', 'No se pudo marcar', ['retry']],
        ['something else', 'No se pudo marcar', ['retry']],
    ])('words %s', (reason, title, actions) => {
        expect(punchProblem(reason)?.title).toBe(title);
        expect(labels(reason)).toEqual(actions);
    });

    it('is not a problem when the mark only came out of order: the next steps are still there', () => {
        for (const reason of [
            'day_already_open',
            'day_not_open',
            'already_on_break',
            'not_on_break',
        ]) {
            expect(isSequence(reason)).toBe(true);
            expect(punchProblem(reason)).toBeNull();
        }

        expect(isSequence('outside_zone')).toBe(false);
    });
});

describe('feedbackProblem', () => {
    it('is the problem of an error with a reason, told with where the location failed', () => {
        expect(
            feedbackProblem(
                { kind: 'error', text: 'x', reason: 'no_location' },
                'timeout',
            )?.title,
        ).toBe('No encontramos su ubicación');
        expect(
            feedbackProblem(
                { kind: 'error', text: 'x', reason: 'outside_zone' },
                null,
            )?.title,
        ).toBe('Está fuera de la zona');
    });

    it('is none for a mark taken, an error with no reason, an order that does not fit, or no answer', () => {
        expect(feedbackProblem({ kind: 'done', text: 'x' }, null)).toBeNull();
        expect(feedbackProblem({ kind: 'error', text: 'x' }, null)).toBeNull();
        expect(
            feedbackProblem(
                { kind: 'error', text: 'x', reason: 'day_not_open' },
                null,
            ),
        ).toBeNull();
        expect(feedbackProblem(null, null)).toBeNull();
    });
});
