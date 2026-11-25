import { describe, expect, it } from 'vitest';
import { offlineReadiness } from '@/lib/attendance/offline-readiness';
import type { ReadinessInput } from '@/lib/attendance/offline-readiness';

const ready: ReadinessInput = {
    context: { offline: true, agreementVersion: 3 },
    tickets: 5,
    camera: 'granted',
    location: 'granted',
    practice: 'passkey',
    passValid: false,
    remote: false,
};

const rowOf = (input: ReadinessInput, key: string) =>
    offlineReadiness(input).rows.find((row) => row.key === key);

describe('offlineReadiness, when it is ready', () => {
    it("is ready with the company's switch on, the agreement accepted and tickets, and every row ok", () => {
        const result = offlineReadiness(ready);

        expect(result.ready).toBe(true);
        expect(result.fewTickets).toBe(false);
        expect(result.rows.every((row) => row.ok)).toBe(true);
    });

    it('warns of few tickets with 1 or 2, and not with 3', () => {
        expect(offlineReadiness({ ...ready, tickets: 1 }).fewTickets).toBe(
            true,
        );
        expect(offlineReadiness({ ...ready, tickets: 2 }).fewTickets).toBe(
            true,
        );
        expect(offlineReadiness({ ...ready, tickets: 3 }).fewTickets).toBe(
            false,
        );
        expect(offlineReadiness({ ...ready, tickets: 1 }).ready).toBe(true);
    });
});

describe('offlineReadiness, what blocks', () => {
    it('is not ready without a context, and says to open the app with internet', () => {
        const input = { ...ready, context: null };

        expect(offlineReadiness(input).ready).toBe(false);
        expect(rowOf(input, 'company')).toMatchObject({
            ok: false,
            blocking: true,
            text: 'Abra Humetría con internet',
            fix: 'open-online',
        });
    });

    it('is not ready when the company has the mode off, and there is nothing the person can do', () => {
        const input = {
            ...ready,
            context: { offline: false, agreementVersion: 3 },
        };

        expect(offlineReadiness(input).ready).toBe(false);
        expect(rowOf(input, 'company')).toMatchObject({
            ok: false,
            blocking: true,
            text: 'Su empresa no tiene activado marcar sin internet',
            fix: null,
        });
    });

    it('is not ready until the agreement is accepted, which the person can do here', () => {
        const input = {
            ...ready,
            context: { offline: true, agreementVersion: null },
        };

        expect(offlineReadiness(input).ready).toBe(false);
        expect(rowOf(input, 'agreement')).toMatchObject({
            ok: false,
            blocking: true,
            fix: 'accept-agreement',
        });
    });

    it('is not ready without tickets, and asks to open the app with internet', () => {
        const input = { ...ready, tickets: 0 };

        expect(offlineReadiness(input).ready).toBe(false);
        expect(rowOf(input, 'tickets')).toMatchObject({
            ok: false,
            blocking: true,
            fix: 'open-online',
        });
    });

    it('has no agreement row to fix without a context: that row waits for it', () => {
        const input = { ...ready, context: null };

        expect(rowOf(input, 'agreement')).toMatchObject({ ok: false });
    });
});

describe('offlineReadiness, what only warns', () => {
    it('warns, without blocking, about a camera that is not allowed, and says how to fix it', () => {
        for (const camera of ['denied', 'prompt', 'unknown'] as const) {
            const input = { ...ready, camera };

            expect(offlineReadiness(input).ready).toBe(true);
            expect(rowOf(input, 'camera')).toMatchObject({
                ok: false,
                blocking: false,
                fix: 'camera',
            });
        }
    });

    it('warns, without blocking, about a location that is not allowed', () => {
        const input = { ...ready, location: 'prompt' as const };

        expect(offlineReadiness(input).ready).toBe(true);
        expect(rowOf(input, 'location')).toMatchObject({
            ok: false,
            blocking: false,
            fix: 'location',
        });
    });

    it('warns, without blocking, when the practice failed and no phone pass is good', () => {
        const input = { ...ready, practice: 'failed' as const };

        expect(offlineReadiness(input).ready).toBe(true);
        expect(rowOf(input, 'signer')).toMatchObject({
            ok: false,
            blocking: false,
            fix: 'prepare-phone',
        });
    });

    it('is fine with a failed practice when the phone has a pass that is good', () => {
        const input = {
            ...ready,
            practice: 'failed' as const,
            passValid: true,
        };

        expect(rowOf(input, 'signer')).toMatchObject({ ok: true });
    });

    it('is fine with no practice at all: the passkey will sign', () => {
        expect(rowOf({ ...ready, practice: null }, 'signer')).toMatchObject({
            ok: true,
        });
    });

    it('has no camera row for a remote person, who reads no code', () => {
        expect(rowOf({ ...ready, remote: true }, 'camera')).toBeUndefined();
        expect(rowOf(ready, 'camera')).toBeDefined();
    });
});
