import { describe, expect, it } from 'vite-plus/test';

import { NOTICE_KINDS, noticeExtras } from '@/lib/telework/notices';

describe('NOTICE_KINDS', () => {
    it('lists the three notices of 6.3, 6.7 and 6.9', () => {
        expect(NOTICE_KINDS.map((kind) => kind.value)).toEqual([
            'condition_change',
            'address_change',
            'work_risk',
        ]);
    });
});

describe('noticeExtras', () => {
    it('asks for the traumatic event only on a change of conditions', () => {
        expect(noticeExtras('condition_change')).toEqual({
            traumatic: true,
            address: false,
        });
    });

    it('asks for the new address, and nothing else, on an address change', () => {
        expect(noticeExtras('address_change')).toEqual({
            traumatic: false,
            address: true,
        });
    });

    it('asks for nothing more on a work risk', () => {
        expect(noticeExtras('work_risk')).toEqual({
            traumatic: false,
            address: false,
        });
    });
});
