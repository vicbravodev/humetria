import type { SignDeps } from '@/lib/attendance/signed-request';
import type { MarkType } from '@/lib/attendance/next-step';
import { canonicalText, challengeHash } from '@/lib/pwa/challenge';
import type { ChallengePosition } from '@/lib/pwa/challenge';
import { signBytes } from '@/lib/pwa/device-key';
import type { OfflineStore } from '@/lib/pwa/offline-store';
import { QUEUE_SCHEMA } from '@/lib/pwa/queue';
import type { DeliveryItem, QueuedMark } from '@/lib/pwa/queue';
import { offlineOptions, signWithPasskey } from '@/lib/pwa/sign-offline';
import { newest, passUsable, without } from '@/lib/pwa/tickets';
import type { OfflineContext, Pass, Ticket } from '@/lib/pwa/tickets';
import { assertionPayload } from '@/lib/webauthn';

/*
 * Composing a mark made without a connection: the ticket it spends, the challenge it signs, the signature (the person's
 * passkey, or the phone's device key when the passkey does not answer) and the one write that keeps the mark, spends the
 * ticket and takes the number. Nothing here touches the network.
 */

export type MarkInput = {
    type: MarkType;
    qr: string | null;
    position: ChallengePosition | null;
    /** Unix seconds: the hour the person says (the phone's at the tap when a QR was read). */
    declaredAt: number;
    justification: string | null;
    useDeviceKey: boolean;
};

/** Why the phone cannot mark without a connection yet. */
export type NotReady = 'no-context' | 'disabled' | 'agreement' | 'tickets';

export type PreparedMark =
    | { kind: 'saved'; mark: QueuedMark }
    | { kind: 'not-ready'; why: NotReady }
    | { kind: 'pass-expired' }
    /** The passkey did not sign; `deviceKey` says whether this phone has a key with a pass that is still good. */
    | { kind: 'cancelled'; deviceKey: boolean }
    | { kind: 'failed' };

export type MarkDeps = {
    store: OfflineStore;
    getKey: SignDeps['getKey'];
    subtle: SubtleCrypto;
    /** The phone's clock, in milliseconds. */
    now: () => number;
};

type Ready = { context: OfflineContext; ticket: Ticket; agreement: number };

function readiness(
    context: OfflineContext | null,
    ticket: Ticket | null,
): Ready | NotReady {
    if (context === null) {
        return 'no-context';
    }

    if (!context.offline) {
        return 'disabled';
    }

    if (context.agreementVersion === null) {
        return 'agreement';
    }

    return ticket === null
        ? 'tickets'
        : { context, ticket, agreement: context.agreementVersion };
}

/** What signs in plan B: the key and its pass, when the pass is still good by the phone's clock. */
type PlanB = { key: CryptoKey; pass: Pass };

function planB(
    key: CryptoKey | null,
    pass: Pass | null,
    now: number,
): PlanB | null {
    return key !== null && pass !== null && passUsable(pass, now)
        ? { key, pass }
        : null;
}

type Signature =
    | { ok: true; fields: Partial<DeliveryItem> }
    | { ok: false; result: PreparedMark };

/** The signature over the hash: with the device key when asked, otherwise with the passkey. */
async function signature(
    input: MarkInput,
    hash: Uint8Array<ArrayBuffer>,
    deps: MarkDeps & { context: OfflineContext; plan: PlanB | null },
): Promise<Signature> {
    const { plan, context } = deps;

    if (input.useDeviceKey && plan !== null) {
        return {
            ok: true,
            fields: {
                signer: 'device_key',
                device_key_id: plan.pass.deviceKeyId,
                signature: await signBytes(deps.subtle, plan.key, hash),
                pass: plan.pass.pass,
            },
        };
    }

    const signed = await signWithPasskey(offlineOptions(hash, context.rpId), {
        getKey: deps.getKey,
        now: deps.now,
    });

    return typeof signed === 'string'
        ? {
              ok: false,
              result: { kind: 'cancelled', deviceKey: plan !== null },
          }
        : {
              ok: true,
              fields: {
                  signer: 'passkey',
                  credential: assertionPayload(signed),
              },
          };
}

/** The mark as the contract of the delivery says, already signed. */
function itemOf(
    input: MarkInput,
    parts: { ready: Ready; seq: number; now: number },
    signed: Partial<DeliveryItem>,
): DeliveryItem {
    const { ready, seq, now } = parts;
    const { position } = input;

    return {
        seq,
        type: input.type,
        ticket: ready.ticket.ticket,
        center_id: ready.context.centerId,
        ...(input.qr === null ? {} : { qr: input.qr }),
        declared_at: input.declaredAt,
        ...(position === null
            ? {}
            : {
                  lat_e5: position.latE5,
                  lng_e5: position.lngE5,
                  accuracy_m: position.accuracyM,
              }),
        device_clock_at: Math.floor(now / 1000),
        ...(input.justification === null
            ? {}
            : { justification: input.justification }),
        agreement_version: ready.agreement,
        signer: 'passkey',
        ...signed,
    };
}

/**
 * Makes a mark without a connection: spends the newest ticket, takes the next `seq`, builds the challenge, has it signed and
 * keeps the mark in one write with the ticket spent. Nothing is spent or numbered unless the write succeeds.
 */
export async function prepareMark(
    input: MarkInput,
    deps: MarkDeps,
): Promise<PreparedMark> {
    const { store, now } = deps;
    const tickets = await store.tickets();
    const ready = readiness(await store.context(), newest(tickets));

    if (typeof ready === 'string') {
        return { kind: 'not-ready', why: ready };
    }

    const plan = planB(await store.deviceKey(), await store.pass(), now());

    if (input.useDeviceKey && plan === null) {
        return { kind: 'pass-expired' };
    }

    const seq = await store.nextSeq();
    const text = canonicalText({
        tenantId: ready.context.tenantId,
        employeeId: ready.context.employeeId,
        centerId: ready.context.centerId,
        type: input.type,
        qr: input.qr,
        declaredAt: input.declaredAt,
        position: input.position,
        agreementVersion: ready.agreement,
        seq,
        passId: input.useDeviceKey && plan !== null ? plan.pass.passId : null,
        ticket: ready.ticket.ticket,
    });

    if (text === null) {
        return { kind: 'failed' };
    }

    const signed = await signature(input, await challengeHash(text), {
        ...deps,
        context: ready.context,
        plan,
    });

    if (!signed.ok) {
        return signed.result;
    }

    const mark: QueuedMark = {
        schema: QUEUE_SCHEMA,
        seq,
        employeeId: ready.context.employeeId,
        createdAt: now(),
        item: itemOf(input, { ready, seq, now: now() }, signed.fields),
        state: 'queued',
        reason: null,
    };

    try {
        await store.commitMark(mark, without(tickets, ready.ticket.ticket));
    } catch {
        return { kind: 'failed' };
    }

    return { kind: 'saved', mark };
}
