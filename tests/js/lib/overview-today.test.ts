import { describe, expect, it } from 'vite-plus/test';

import { billedColumns, greeting } from '@/lib/overview-today';

describe('greeting', () => {
    it('greets by the time of day, by first name', () => {
        expect(greeting(8, 'Victor Bravo')).toBe('Buenos días, Victor');
        expect(greeting(13, 'Victor Bravo')).toBe('Buenas tardes, Victor');
        expect(greeting(20, 'Victor Bravo')).toBe('Buenas noches, Victor');
        expect(greeting(5, '')).toBe('Buenas noches');
    });

    it('turns at noon and at seven in the evening', () => {
        expect(greeting(11, 'Ana')).toBe('Buenos días, Ana');
        expect(greeting(12, 'Ana')).toBe('Buenas tardes, Ana');
        expect(greeting(18, 'Ana')).toBe('Buenas tardes, Ana');
        expect(greeting(19, 'Ana')).toBe('Buenas noches, Ana');
    });
});

describe('billedColumns', () => {
    it('draws each month against the highest, named by its short month, the last one marked as the current', () => {
        const columns = billedColumns([
            { month: '2026-10', cents: 5_000_000 },
            { month: '2026-11', cents: 10_000_000 },
        ]);

        expect(
            columns.map(({ label, percent, valueText }) => [
                label,
                percent,
                valueText,
            ]),
        ).toEqual([
            ['oct', 50, '$50k'],
            ['nov', 100, '$100k'],
        ]);
        expect(columns[1]?.tooltip).toBe('noviembre 2026: $100,000 facturado');
    });

    it('draws flat columns when nothing was billed', () => {
        expect(
            billedColumns([{ month: '2026-11', cents: 0 }])[0],
        ).toMatchObject({ percent: 0, valueText: '$0' });
    });
});
