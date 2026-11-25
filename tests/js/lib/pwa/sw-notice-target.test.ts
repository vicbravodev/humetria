import { describe, expect, it } from 'vitest';
import { noticeTarget } from '@/lib/pwa/sw-rules';

describe('noticeTarget', () => {
    const scope = 'https://h/me/acme';

    it('groups the notices of a kind under one tag and sends each to the screen it is about', () => {
        expect(noticeTarget({ kind: 'mark_reviewed' }, scope)).toEqual({
            tag: 'mark_reviewed',
            url: 'https://h/me/acme/week',
        });
        expect(noticeTarget({ kind: 'correction_resolved' }, scope)).toEqual({
            tag: 'correction_resolved',
            url: 'https://h/me/acme/week',
        });
    });

    it.each([
        ['no body at all', undefined],
        ['a kind it does not know', { kind: 'something_else' }],
        ['the name of an object property', { kind: 'constructor' }],
    ])(
        'sends %s to Marcar, under the tag of the fixed notice',
        (_, payload) => {
            expect(noticeTarget(payload, scope)).toEqual({
                tag: 'notice',
                url: 'https://h/me/acme/punch',
            });
        },
    );
});
