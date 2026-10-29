import { describe, expect, it } from 'vite-plus/test';

import { firstInvalidField } from '@/lib/first-invalid-field';

const fields = [
    { name: 'name' },
    { name: 'company' },
    { name: 'profile' },
    { name: 'profile' },
    { name: 'tools[]' },
    { name: 'tools[]' },
    { name: 'message' },
];

describe('firstInvalidField', () => {
    it('is the first field, in page order, that has an error', () => {
        expect(firstInvalidField(fields, { message: 'x', company: 'y' })).toBe(
            fields[1],
        );
    });

    it('is the first option of an invalid group', () => {
        expect(firstInvalidField(fields, { profile: 'x' })).toBe(fields[2]);
    });

    it('is the first checkbox of a list whose error is keyed without the brackets', () => {
        expect(firstInvalidField(fields, { tools: 'x' })).toBe(fields[4]);
    });

    it('is null when no field has an error', () => {
        expect(firstInvalidField(fields, {})).toBeNull();
    });

    it('is null when the errors are for fields the page does not show', () => {
        expect(firstInvalidField(fields, { other: 'x' })).toBeNull();
    });
});
