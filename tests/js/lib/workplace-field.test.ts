import { describe, expect, it } from 'vite-plus/test';

import { workplaceField } from '@/lib/workplace-field';

describe('workplaceField', () => {
    it('names a bare field when there is a single workplace', () => {
        expect(workplaceField(null, 'street')).toEqual({
            name: 'street',
            error: 'street',
        });
    });

    it('names a field of a list in the form and in the errors', () => {
        expect(workplaceField(0, 'postal_code')).toEqual({
            name: 'workplaces[0][postal_code]',
            error: 'workplaces.0.postal_code',
        });
        expect(workplaceField(2, 'state')).toEqual({
            name: 'workplaces[2][state]',
            error: 'workplaces.2.state',
        });
    });
});
