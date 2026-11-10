import { describe, expect, it } from 'vitest';
import { parsePreview, previewError, previewQuery } from '@/lib/leave/preview';

describe('previewQuery', () => {
    it('asks once both days are picked, with the leave type when there is one', () => {
        expect(previewQuery('2026-11-09', '2026-11-20', '')).toEqual({
            starts_on: '2026-11-09',
            ends_on: '2026-11-20',
        });
        expect(previewQuery('2026-11-09', '2026-11-09', 't1')).toEqual({
            starts_on: '2026-11-09',
            ends_on: '2026-11-09',
            leave_type_id: 't1',
        });
    });

    it('does not ask without both days, nor with the last before the first', () => {
        expect(previewQuery('', '2026-11-20', '')).toBeNull();
        expect(previewQuery('2026-11-09', '', '')).toBeNull();
        expect(previewQuery('2026-11-20', '2026-11-09', '')).toBeNull();
    });
});

describe('parsePreview', () => {
    it('reads what the dates would be as a request', () => {
        expect(
            parsePreview({
                workingDays: 9,
                hadBalance: false,
                shortBy: 4,
                continuousNotice: true,
            }),
        ).toEqual({
            workingDays: 9,
            hadBalance: false,
            shortBy: 4,
            continuousNotice: true,
        });
        expect(
            parsePreview({
                workingDays: 2,
                hadBalance: null,
                shortBy: 0,
                continuousNotice: false,
            })?.hadBalance,
        ).toBeNull();
    });

    it.each([
        null,
        'nine',
        {},
        { workingDays: '9', hadBalance: true, continuousNotice: false },
        { workingDays: 9, hadBalance: 'yes', continuousNotice: false },
        { workingDays: 9, hadBalance: true, continuousNotice: 1 },
    ])('reads nothing from %j', (value) => {
        expect(parsePreview(value)).toBeNull();
    });
});

describe('previewError', () => {
    it('reads the first reason the server gives', () => {
        expect(
            previewError({
                message: 'x',
                errors: {
                    ends_on: ['Esas fechas no incluyen ningún día laborable.'],
                },
            }),
        ).toBe('Esas fechas no incluyen ningún día laborable.');
    });

    it.each([null, {}, { errors: [] }, { errors: { ends_on: [] } }])(
        'reads nothing from %j',
        (value) => {
            expect(previewError(value)).toBeNull();
        },
    );
});
