import { describe, expect, it } from 'vitest';
import {
    followUpTitle,
    parseFollowUp,
    senderLabel,
} from '@/lib/complaints/follow-up';

const valid = {
    status: 'in_review',
    statusLabel: 'En revisión',
    resolution: null,
    handlers: 2,
    canReply: true,
    canWithdraw: true,
    receivedOn: '2026-11-02',
    acknowledgedOn: null,
    respondBy: '2026-12-02',
    entries: {
        data: [{ from: 'handlers', text: 'Hola', day: '2026-11-20' }],
        current_page: 1,
        last_page: 3,
        total: 41,
    },
};

describe('parseFollowUp', () => {
    it('reads what the server answers', () => {
        expect(parseFollowUp(valid)).toEqual(valid);
    });

    it('keeps the day someone read it', () => {
        expect(
            parseFollowUp({ ...valid, acknowledgedOn: '2026-11-04' })
                ?.acknowledgedOn,
        ).toBe('2026-11-04');
    });

    it('keeps a resolution in words', () => {
        expect(
            parseFollowUp({
                ...valid,
                status: 'closed',
                resolution: 'La empresa tomó medidas.',
            })?.resolution,
        ).toBe('La empresa tomó medidas.');
    });

    it.each([
        ['nothing', undefined],
        ['text', 'x'],
        ['a missing status', { ...valid, status: undefined }],
        ['an unknown status', { ...valid, status: 'weird' }],
        ['handlers that are not a count', { ...valid, handlers: 'two' }],
        ['no day it came', { ...valid, receivedOn: undefined }],
        [
            'a day it was read that is not a day',
            { ...valid, acknowledgedOn: 4 },
        ],
        ['no day of the answer', { ...valid, respondBy: null }],
        ['entries that are not a page', { ...valid, entries: [] }],
        [
            'an entry from nobody',
            {
                ...valid,
                entries: {
                    ...valid.entries,
                    data: [{ from: 'x', text: 'a', day: '2026-11-20' }],
                },
            },
        ],
        [
            'an entry without words',
            {
                ...valid,
                entries: {
                    ...valid.entries,
                    data: [{ from: 'handlers', text: null, day: '2026-11-20' }],
                },
            },
        ],
    ])('reads nothing from %s', (_name, value) => {
        expect(parseFollowUp(value)).toBeUndefined();
    });
});

describe('senderLabel', () => {
    it('says who wrote, never a name', () => {
        expect(senderLabel('reporter')).toBe('Usted');
        expect(senderLabel('handlers')).toBe('Persona encargada');
    });
});

describe('followUpTitle', () => {
    it('says where the complaint stands', () => {
        expect(followUpTitle('received')).toBe('Recibimos su queja');
        expect(followUpTitle('in_review')).toBe('Su queja está en revisión');
        expect(followUpTitle('closed')).toBe('Su queja se cerró');
    });
});
