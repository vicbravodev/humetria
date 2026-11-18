import { describe, expect, it } from 'vitest';
import {
    filmWaitsForVisitor,
    loopAction,
    readNetworkHints,
    type LoopState,
} from '@/lib/loop-video';

const base: LoopState = {
    visible: true,
    paused: true,
    pausedByVisitor: false,
    still: false,
};

describe('loopAction', () => {
    it('plays a film that comes into view, so nothing downloads for one nobody scrolls to', () => {
        expect(loopAction(base)).toBe('play');
    });

    it('pauses a film that left the view, so it stops decoding and loading off screen', () => {
        expect(loopAction({ ...base, visible: false, paused: false })).toBe(
            'pause',
        );
    });

    it('leaves a film alone when it already does what the view asks', () => {
        expect(loopAction({ ...base, paused: false })).toBe('keep');
        expect(loopAction({ ...base, visible: false })).toBe('keep');
    });

    it('never restarts a film the visitor paused, when it comes back into view', () => {
        expect(loopAction({ ...base, pausedByVisitor: true })).toBe('keep');
    });

    it('still pauses a playing film out of view even if the visitor had started it', () => {
        expect(
            loopAction({
                visible: false,
                paused: false,
                pausedByVisitor: false,
                still: false,
            }),
        ).toBe('pause');
    });

    it('never starts a film by itself with reduced motion: it waits for the visitor on its poster', () => {
        expect(loopAction({ ...base, still: true })).toBe('keep');
    });

    it('still pauses, with reduced motion, a film the visitor started once it leaves the view', () => {
        expect(
            loopAction({ ...base, visible: false, paused: false, still: true }),
        ).toBe('pause');
    });
});

describe('readNetworkHints', () => {
    it('reads what the browser says about the connection, when it says anything', () => {
        expect(
            readNetworkHints({
                connection: { saveData: true, effectiveType: '3g' },
            }),
        ).toEqual({ saveData: true, effectiveType: '3g' });
    });

    it('is empty where the browser gives nothing: Safari and Firefox have no connection hints', () => {
        expect(readNetworkHints({})).toEqual({});
        expect(readNetworkHints({ connection: null })).toEqual({});
        expect(readNetworkHints({ connection: 'fast' })).toEqual({});
    });

    it('ignores what is not the right type, so a strange value never decides anything', () => {
        expect(
            readNetworkHints({
                connection: { saveData: 'yes', effectiveType: 4 },
            }),
        ).toEqual({});
    });
});

describe('filmWaitsForVisitor', () => {
    it('holds the film back on a poster when the visitor asked to save data', () => {
        expect(filmWaitsForVisitor({ saveData: true })).toBe(true);
    });

    it('holds it back on a slow connection, where several megabytes of video would only get in the way', () => {
        for (const effectiveType of ['slow-2g', '2g', '3g']) {
            expect(filmWaitsForVisitor({ effectiveType })).toBe(true);
        }
    });

    it('lets it play on a fast connection, and when the browser says nothing', () => {
        expect(filmWaitsForVisitor({ effectiveType: '4g' })).toBe(false);
        expect(filmWaitsForVisitor({ saveData: false })).toBe(false);
        expect(filmWaitsForVisitor({})).toBe(false);
    });
});
