import { describe, expect, it, vi } from 'vitest';
import { canonicalText, challengeHash } from '@/lib/pwa/challenge';
import { prepareMark } from '@/lib/pwa/offline-mark';
import {
    NOW,
    context,
    depsOf,
    input,
    key,
    readyStore,
} from '../../support/offline-mark';

describe('prepareMark, the mark it saves', () => {
    it('is as the contract says, signed over the hash of its canonical text', async () => {
        const { store } = await readyStore(1);
        const getKey = vi.fn((options: PublicKeyCredentialRequestOptions) => {
            void options;

            return Promise.resolve(key);
        });
        const saved = await prepareMark(
            { ...input, qr: '29652320.ab', justification: 'Se fue la luz' },
            depsOf(store, getKey),
        );

        if (saved.kind !== 'saved') {
            throw new Error('Not saved.');
        }

        const { mark } = saved;

        expect(mark).toMatchObject({
            schema: 1,
            seq: 1,
            employeeId: 'ana',
            createdAt: NOW,
            state: 'queued',
            reason: null,
        });
        expect(mark.item).toMatchObject({
            seq: 1,
            type: 'start',
            ticket: 'v1.t0.mac',
            center_id: 'center-1',
            qr: '29652320.ab',
            declared_at: 1_794_050_400,
            lat_e5: 1916543,
            lng_e5: -9913270,
            accuracy_m: 12,
            device_clock_at: Math.floor(NOW / 1000),
            justification: 'Se fue la luz',
            agreement_version: 3,
            signer: 'passkey',
        });
        expect(mark.item.credential).toMatchObject({
            id: 'CQ',
            response: { signature: 'Aw' },
        });
        expect(mark.item).not.toHaveProperty('device_key_id');
    });

    it("asks the device to sign the hash of that text, for the company's relying party", async () => {
        const { store } = await readyStore(1);
        const getKey = vi.fn((options: PublicKeyCredentialRequestOptions) => {
            void options;

            return Promise.resolve(key);
        });

        await prepareMark(input, depsOf(store, getKey));

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
            passId: null,
            ticket: 'v1.t0.mac',
        });
        const asked = getKey.mock.calls[0]?.[0];

        expect(text).not.toBeNull();
        expect(asked?.rpId).toBe('humetria.test');
        expect(asked?.userVerification).toBe('required');
        expect(asked?.challenge).toEqual(await challengeHash(text ?? ''));
    });

    it('leaves out what it does not have: no position, no QR, no note', async () => {
        const { store } = await readyStore(1);
        const saved = await prepareMark(
            { ...input, position: null },
            depsOf(store),
        );
        const item = saved.kind === 'saved' ? saved.mark.item : null;

        expect(item).not.toBeNull();
        expect(item).not.toHaveProperty('lat_e5');
        expect(item).not.toHaveProperty('accuracy_m');
        expect(item).not.toHaveProperty('qr');
        expect(item).not.toHaveProperty('justification');
    });
});
