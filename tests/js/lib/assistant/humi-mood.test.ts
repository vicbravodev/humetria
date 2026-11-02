import { describe, expect, it } from 'vite-plus/test';
import { moodOf } from '@/lib/assistant/humi-mood';

const rest = {
    busy: false,
    failed: false,
    writing: false,
    finished: false,
};

describe('moodOf', () => {
    it('rests with hola', () => {
        expect(moodOf(rest)).toBe('hola');
    });

    it('listens while the person writes', () => {
        expect(moodOf({ ...rest, writing: true })).toBe('escucha');
    });

    it('calculates while it answers, even if the person is still in the box', () => {
        expect(moodOf({ ...rest, busy: true, writing: true })).toBe('calcula');
    });

    it('is listo for a moment once it finished, and listens again if the person writes', () => {
        expect(moodOf({ ...rest, finished: true })).toBe('listo');
        expect(moodOf({ ...rest, finished: true, writing: true })).toBe(
            'escucha',
        );
    });

    it('listens, calm, when a message did not get an answer', () => {
        expect(moodOf({ ...rest, failed: true, finished: true })).toBe(
            'escucha',
        );
    });
});
