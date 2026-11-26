import { describe, expect, it } from 'vitest';
import {
    MAX_TICKETS,
    contextIn,
    merge,
    newest,
    passIn,
    passUsable,
    renewalDue,
    stock,
    ticketsIn,
    without,
} from '@/lib/pwa/tickets';
import type { Ticket } from '@/lib/pwa/tickets';

function ticket(name: string, issuedAt = 100): Ticket {
    return { ticket: `v1.${name}.mac`, issuedAt };
}

const context = {
    tenantId: 't',
    employeeId: 'e',
    centerId: 'c',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
    qrTypes: ['start', 'end'],
};

describe('ticketsIn', () => {
    it('reads the tickets of any answer, and none of one that carries none', () => {
        expect(
            ticketsIn({ tickets: [ticket('a'), ticket('b', 200)], day: 1 }),
        ).toEqual([ticket('a'), ticket('b', 200)]);
        expect(ticketsIn({ day: 1 })).toEqual([]);
        expect(ticketsIn({ tickets: 'a' })).toEqual([]);
        expect(ticketsIn(null)).toEqual([]);
        expect(ticketsIn([])).toEqual([]);
    });

    it('leaves out the entries it cannot read', () => {
        expect(
            ticketsIn({
                tickets: [
                    ticket('a'),
                    { ticket: 7, issuedAt: 1 },
                    { ticket: 'x', issuedAt: 'now' },
                    null,
                    'v1.x.y',
                    { ticket: '', issuedAt: 1 },
                ],
            }),
        ).toEqual([ticket('a')]);
    });
});

describe('contextIn', () => {
    it('reads the context of the answer', () => {
        expect(contextIn({ context })).toEqual(context);
    });

    it('reads that the person has not accepted the agreement as null', () => {
        expect(
            contextIn({ context: { ...context, agreementVersion: null } })
                ?.agreementVersion,
        ).toBeNull();
    });

    it('reads a context kept before the marks that ask for the QR were known as one that asks for none', () => {
        const old = Object.fromEntries(
            Object.entries(context).filter(([key]) => key !== 'qrTypes'),
        );

        expect(contextIn({ context: old })?.qrTypes).toEqual([]);
    });

    it('keeps only the names of marks in the list that asks for the QR', () => {
        expect(
            contextIn({ context: { ...context, qrTypes: ['start', 7, 'end'] } })
                ?.qrTypes,
        ).toEqual(['start', 'end']);
    });

    it('gives nothing for a context that is missing or damaged', () => {
        expect(contextIn({})).toBeNull();
        expect(contextIn(null)).toBeNull();
        expect(contextIn({ context: 'x' })).toBeNull();
        expect(
            contextIn({ context: { ...context, offline: 'yes' } }),
        ).toBeNull();
        expect(contextIn({ context: { ...context, rpId: 7 } })).toBeNull();
        expect(contextIn({ context: { ...context, tenantId: 7 } })).toBeNull();
        expect(
            contextIn({ context: { ...context, employeeId: 7 } }),
        ).toBeNull();
        expect(contextIn({ context: { ...context, centerId: 7 } })).toBeNull();
        expect(
            contextIn({ context: { ...context, agreementVersion: 'three' } }),
        ).toBeNull();
    });
});

describe('passIn', () => {
    const pass = {
        deviceKeyId: 'k',
        pass: 'v1.p.m',
        passId: 'p',
        passExpiresAt: '2026-11-25T10:00:00-06:00',
    };

    it('reads the pass of a registration or a renewal', () => {
        expect(passIn(pass)).toEqual(pass);
        expect(passIn({ ...pass, extra: 1 })).toEqual(pass);
    });

    it('gives nothing for a partial pass', () => {
        expect(passIn(null)).toBeNull();
        expect(passIn({ ...pass, passId: undefined })).toBeNull();
        expect(passIn({ ...pass, pass: 5 })).toBeNull();
        expect(passIn({ ...pass, deviceKeyId: null })).toBeNull();
        expect(passIn({ ...pass, passExpiresAt: 1_700_000_000 })).toBeNull();
    });
});

describe('merge', () => {
    it('has no duplicates and puts the newest first', () => {
        const merged = merge(
            [ticket('a', 100), ticket('b', 100)],
            [ticket('b', 100), ticket('c', 300)],
        );

        expect(merged.map((t) => t.ticket)).toEqual([
            'v1.c.mac',
            'v1.a.mac',
            'v1.b.mac',
        ]);
    });

    it('keeps 16: when 17 come in, the oldest leaves', () => {
        const old = Array.from({ length: 9 }, (_, i) => ticket(`o${i}`, 100));
        const fresh = Array.from({ length: 8 }, (_, i) => ticket(`n${i}`, 200));
        const merged = merge(old, fresh);

        expect(MAX_TICKETS).toBe(16);
        expect(merged).toHaveLength(16);
        expect(merged.slice(0, 8)).toEqual(fresh);
        expect(merged.some((t) => t.ticket === 'v1.o8.mac')).toBe(false);
    });

    it('takes a cap of its own', () => {
        expect(merge([ticket('a')], [ticket('b', 200)], 1)).toEqual([
            ticket('b', 200),
        ]);
    });
});

describe('newest and without', () => {
    it('spends the newest ticket that is left and drops it', () => {
        const tickets = merge([ticket('a', 100)], [ticket('b', 200)]);

        expect(newest(tickets)).toEqual(ticket('b', 200));
        expect(without(tickets, 'v1.b.mac')).toEqual([ticket('a', 100)]);
        expect(newest(without(tickets, 'v1.b.mac'))).toEqual(ticket('a', 100));
    });

    it('has none to spend when there are none', () => {
        expect(newest([])).toBeNull();
        expect(without([], 'v1.b.mac')).toEqual([]);
    });
});

describe('stock', () => {
    it('is none with 0, low with 1 or 2 and ok with 3', () => {
        expect(stock([])).toBe('none');
        expect(stock([ticket('a')])).toBe('low');
        expect(stock([ticket('a'), ticket('b')])).toBe('low');
        expect(stock([ticket('a'), ticket('b'), ticket('c')])).toBe('ok');
    });
});

describe('renewalDue', () => {
    it('is due the first time, not at 29 s and due at 30 s', () => {
        expect(renewalDue(null, 1_000_000)).toBe(true);
        expect(renewalDue(1_000_000, 1_029_000)).toBe(false);
        expect(renewalDue(1_000_000, 1_030_000)).toBe(true);
    });
});

describe('passUsable', () => {
    const pass = {
        deviceKeyId: 'k',
        pass: 'v1.p.m',
        passId: 'p',
        passExpiresAt: '2026-11-25T10:00:00.000Z',
    };
    const expiry = Date.parse(pass.passExpiresAt);

    it('is usable before it expires, not at that instant nor after', () => {
        expect(passUsable(pass, expiry - 1)).toBe(true);
        expect(passUsable(pass, expiry)).toBe(false);
        expect(passUsable(pass, expiry + 1)).toBe(false);
    });

    it('is not usable when its expiry cannot be read, and there is none without a pass', () => {
        expect(passUsable({ ...pass, passExpiresAt: 'soon' }, 0)).toBe(false);
        expect(passUsable(null, 0)).toBe(false);
    });
});
