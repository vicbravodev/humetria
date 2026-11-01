import { describe, expect, it } from 'vite-plus/test';

import { nothingPending } from '@/lib/telework/portal';
import type { PortalPending } from '@/types';

const empty: PortalPending = {
    checklists: [],
    sent: [],
    photos: [],
    visits: [],
    documents: [],
    equipment: [],
};

describe('nothingPending', () => {
    it('is true when every list is empty', () => {
        expect(nothingPending(empty)).toBe(true);
    });

    it('is false when any list has something, documents included', () => {
        expect(
            nothingPending({
                ...empty,
                documents: [
                    { id: 'd', title: 'Política', publishedOn: '2026-05-04' },
                ],
            }),
        ).toBe(false);
        expect(
            nothingPending({
                ...empty,
                visits: [{ id: 'v', workplace: 'Casa' }],
            }),
        ).toBe(false);
    });
});
