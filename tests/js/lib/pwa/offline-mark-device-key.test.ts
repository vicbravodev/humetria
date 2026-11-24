import { describe, expect, it } from 'vitest';
import { canonicalText, challengeHash } from '@/lib/pwa/challenge';
import { createDeviceKey } from '@/lib/pwa/device-key';
import { prepareMark } from '@/lib/pwa/offline-mark';
import { fromBase64url } from '@/lib/webauthn';
import {
    context,
    depsOf,
    input,
    readyStore,
    refuses,
} from '../../support/offline-mark';

const FUTURE = '2026-11-26T09:00:00Z';
const PAST = '2026-11-24T09:00:00Z';

async function withDeviceKey(passExpiresAt: string) {
    const ready = await readyStore(2);
    const { privateKey, spki } = await createDeviceKey(crypto.subtle);
    await ready.store.saveDeviceKey(privateKey);
    await ready.store.savePass({
        deviceKeyId: 'device-1',
        pass: 'v1.pass.mac',
        passId: 'pass-1',
        passExpiresAt,
    });

    return { ...ready, spki };
}

describe('prepareMark, with the device key (plan B)', () => {
    it('offers the device key when the passkey is cancelled and a pass is good', async () => {
        const { store } = await withDeviceKey(FUTURE);

        expect(await prepareMark(input, depsOf(store, refuses))).toEqual({
            kind: 'cancelled',
            deviceKey: true,
        });
    });

    it('signs with the device key, putting the pass id in the challenge and the pass in the mark', async () => {
        const { store, spki } = await withDeviceKey(FUTURE);
        const saved = await prepareMark(
            { ...input, useDeviceKey: true },
            depsOf(store, refuses),
        );

        if (saved.kind !== 'saved') {
            throw new Error('Not saved.');
        }

        const { item } = saved.mark;

        expect(item).toMatchObject({
            signer: 'device_key',
            device_key_id: 'device-1',
            pass: 'v1.pass.mac',
        });
        expect(item).not.toHaveProperty('credential');

        const text = canonicalText({
            tenantId: context.tenantId,
            employeeId: context.employeeId,
            centerId: context.centerId,
            type: 'start',
            qr: null,
            declaredAt: input.declaredAt,
            position: input.position,
            agreementVersion: 3,
            seq: 1,
            passId: 'pass-1',
            ticket: item.ticket,
        });
        const publicKey = await crypto.subtle.importKey(
            'spki',
            fromBase64url(spki),
            { name: 'ECDSA', namedCurve: 'P-256' },
            false,
            ['verify'],
        );

        expect(
            await crypto.subtle.verify(
                { name: 'ECDSA', hash: 'SHA-256' },
                publicKey,
                fromBase64url(item.signature ?? ''),
                await challengeHash(text ?? ''),
            ),
        ).toBe(true);
    });

    it("says the pass expired when the phone's clock says so, and does not spend the ticket", async () => {
        const { store } = await withDeviceKey(PAST);

        expect(
            await prepareMark({ ...input, useDeviceKey: true }, depsOf(store)),
        ).toEqual({ kind: 'pass-expired' });
        expect((await store.tickets()).length).toBe(2);
    });

    it('says the pass expired when there is no device key, and offers none when the pass is old', async () => {
        const { store } = await readyStore(1);

        expect(
            await prepareMark({ ...input, useDeviceKey: true }, depsOf(store)),
        ).toEqual({ kind: 'pass-expired' });

        const old = await withDeviceKey(PAST);

        expect(await prepareMark(input, depsOf(old.store, refuses))).toEqual({
            kind: 'cancelled',
            deviceKey: false,
        });
    });
});

describe('forgetting the person after a mark', () => {
    it('takes out their tickets, pass, key, practice and context, and keeps the queue', async () => {
        const { store, kv } = await withDeviceKey(FUTURE);
        await store.savePractice({ result: 'passkey', at: 1 });
        await prepareMark(input, depsOf(store));

        await store.forgetPerson();

        expect(await store.tickets()).toEqual([]);
        expect(await store.context()).toBeNull();
        expect(await store.pass()).toBeNull();
        expect(await store.practice()).toBeNull();
        expect((await store.marks()).map((m) => m.seq)).toEqual([1]);
        expect(kv.data.has('mark/1')).toBe(true);
    });
});
