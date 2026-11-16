import { describe, expect, it } from 'vite-plus/test';

import { panelListQuery } from '@/lib/panel-list';

describe('the address of a list of the panel by person', () => {
    it('carries only what differs from the default view', () => {
        expect(panelListQuery({ q: '  ', status: 'all', center: '' })).toEqual(
            {},
        );
        expect(
            panelListQuery({ q: ' Ana ', status: 'invited', center: 'c-1' }),
        ).toEqual({ q: 'Ana', status: 'invited', center: 'c-1' });
    });
});
