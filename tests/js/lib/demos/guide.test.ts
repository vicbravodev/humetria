import { describe, expect, it } from 'vite-plus/test';

import {
    celebrationOf,
    continueLabel,
    guideAnswer,
    guideCount,
    isDemoGuide,
    nextCelebration,
    progressLabel,
    shownCelebration,
    spotlightFor,
} from '@/lib/demos/guide';
import type { DemoGuide, DemoGuideStep } from '@/types/demo-guide';

const launch: DemoGuideStep = {
    key: 'nom035.launch',
    tour: 'NOM-035',
    title: 'Lanza la evaluación',
    todo: 'Revisa la evaluación y lánzala.',
    why: 'Cada persona recibe su invitación.',
    kind: 'do',
    href: 'http://localhost/admin/alba-demo/campaigns/9f1c',
    target: 'launch-evaluation',
    ask: null,
    live: null,
    count: null,
    of: null,
    noun: null,
    index: 2,
    total: 6,
};

const answers: DemoGuideStep = {
    ...launch,
    key: 'nom035.answers',
    title: 'El público contesta',
    kind: 'watch',
    target: 'evaluation-progress',
    live: 'Llegaron 3 de 5 respuestas',
    count: 3,
    of: 5,
    noun: 'respuestas',
    index: 3,
};

const guide: DemoGuide = {
    tours: [
        { key: 'base', title: 'El centro y su gente', done: 2, total: 2 },
        { key: 'nom035', title: 'NOM-035', done: 1, total: 6 },
    ],
    current: launch,
    finished: false,
    justDone: null,
};

describe('spotlightFor', () => {
    it('lights the step control only on the step page', () => {
        expect(spotlightFor(launch, '/admin/alba-demo/campaigns/9f1c')).toEqual(
            { selector: '[data-tour="launch-evaluation"]' },
        );
        expect(
            spotlightFor(launch, '/admin/alba-demo/campaigns/9f1c?tab=people'),
        ).toEqual({ selector: '[data-tour="launch-evaluation"]' });
        expect(spotlightFor(launch, '/admin/alba-demo/campaigns')).toBeNull();
    });

    it('lights nothing for a step without a control', () => {
        expect(
            spotlightFor(
                { ...launch, target: null },
                '/admin/alba-demo/campaigns/9f1c',
            ),
        ).toBeNull();
    });
});

describe('progressLabel', () => {
    it('says the step within its tour', () => {
        expect(progressLabel(launch)).toBe('Paso 2 de 6 · NOM-035');
    });
});

describe('guideCount', () => {
    it('counts the step the session is on over all its steps', () => {
        expect(guideCount(guide)).toBe('4 de 8');
    });

    it('counts every step once the session finished', () => {
        expect(
            guideCount({
                ...guide,
                current: null,
                finished: true,
                tours: [{ key: 'base', title: 'x', done: 2, total: 2 }],
            }),
        ).toBe('2 de 2');
    });
});

describe('continueLabel', () => {
    it('moves on with what came in, on a step the audience does', () => {
        expect(continueLabel(answers)).toBe('Seguir con 3 respuestas');
        expect(continueLabel({ ...answers, count: 1, noun: 'respuesta' })).toBe(
            'Seguir con 1 respuesta',
        );
        expect(
            continueLabel({
                ...answers,
                count: 4,
                of: null,
                noun: 'marcas en el centro',
            }),
        ).toBe('Seguir con 4 marcas en el centro');
    });

    it('skips when nothing came in, nothing is counted or the presenter acts', () => {
        expect(continueLabel({ ...answers, count: 0 })).toBeNull();
        expect(
            continueLabel({ ...answers, count: null, of: null, noun: null }),
        ).toBeNull();
        expect(continueLabel({ ...answers, kind: 'do' })).toBeNull();
    });
});

describe('guideAnswer', () => {
    it('takes the guide, or no guide when the server says there is none', () => {
        expect(guideAnswer(200, guide, true)).toEqual(guide);
        expect(guideAnswer(200, null, true)).toBeNull();
        expect(guideAnswer(404, undefined, true)).toBeNull();
        expect(guideAnswer(403, undefined, true)).toBeNull();
        expect(guideAnswer(401, undefined, true)).toBeNull();
    });

    it('keeps the guide on anything else: a skip refused, a server error, a page that is not the guide', () => {
        expect(guideAnswer(404, undefined, false)).toBeUndefined();
        expect(guideAnswer(500, undefined, true)).toBeUndefined();
        expect(guideAnswer(429, undefined, true)).toBeUndefined();
        expect(guideAnswer(200, undefined, true)).toBeUndefined();
        expect(guideAnswer(200, { tours: 'x' }, true)).toBeUndefined();
    });
});

describe('celebrationOf', () => {
    it('celebrates the step just done', () => {
        expect(
            celebrationOf({ ...guide, justDone: 'Lanza la evaluación' }),
        ).toBe('Listo: «Lanza la evaluación».');
        expect(celebrationOf(guide)).toBeNull();
    });

    it('says the session is over once every tour is', () => {
        expect(celebrationOf({ ...guide, current: null, finished: true })).toBe(
            'Terminaste los 2 recorridos de esta sesión.',
        );
        expect(
            celebrationOf({
                ...guide,
                tours: [{ key: 'base', title: 'x', done: 2, total: 2 }],
                current: null,
                finished: true,
            }),
        ).toBe('Terminaste el recorrido de esta sesión.');
    });
});

describe('isDemoGuide', () => {
    it('takes the guide the server sends', () => {
        expect(isDemoGuide(guide)).toBe(true);
        expect(isDemoGuide({ ...guide, current: null, finished: true })).toBe(
            true,
        );
    });

    it('refuses anything else: no session, a login page, a broken shape', () => {
        expect(isDemoGuide(null)).toBe(false);
        expect(isDemoGuide('<html>')).toBe(false);
        expect(isDemoGuide({ ...guide, tours: 'x' })).toBe(false);
        expect(isDemoGuide({ ...guide, finished: 'no' })).toBe(false);
        expect(isDemoGuide({ ...guide, current: { key: 'x' } })).toBe(false);
        expect(
            isDemoGuide({ ...guide, current: { ...launch, kind: 'other' } }),
        ).toBe(false);
        expect(
            isDemoGuide({ ...guide, current: { ...answers, count: '3' } }),
        ).toBe(false);
        expect(isDemoGuide({ ...guide, tours: [{ key: 'x' }] })).toBe(false);
    });
});

describe('nextCelebration and shownCelebration', () => {
    const answering: DemoGuide = {
        ...guide,
        current: answers,
        justDone: 'Lanza la evaluación',
    };

    it('holds the step just done while the guide stays on the next one', () => {
        const held = nextCelebration(null, answering);

        expect(held).toEqual({
            text: 'Listo: «Lanza la evaluación».',
            key: 'nom035.answers',
        });
        expect(nextCelebration(held, answering)).toBe(held);

        const polled = { ...answering, justDone: null };

        expect(nextCelebration(held, polled)).toBe(held);
        expect(shownCelebration(held, polled)).toBe(
            'Listo: «Lanza la evaluación».',
        );
    });

    it('lets it go once the guide moves on, and celebrates nothing without a guide', () => {
        const held = nextCelebration(null, answering);

        expect(shownCelebration(held, { ...guide, justDone: null })).toBeNull();
        expect(shownCelebration(null, answering)).toBeNull();
        expect(nextCelebration(null, null)).toBeNull();
        expect(shownCelebration(held, null)).toBeNull();
    });
});
