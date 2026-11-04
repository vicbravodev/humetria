import { describe, expect, it } from 'vitest';
import { parseFollowUp, senderLabel } from '@/lib/complaints/follow-up';

const valid = {
    status: 'in_review',
    statusLabel: 'En revisión',
    resolution: null,
    handlers: 2,
    canReply: true,
    canWithdraw: true,
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
