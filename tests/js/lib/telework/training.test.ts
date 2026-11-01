import { describe, expect, it } from 'vite-plus/test';

import { attendeesText, standingTone } from '@/lib/telework/training';

describe('standingTone', () => {
    it('is calm when up to date, warns when due soon and blocks when overdue', () => {
        expect(standingTone('ok')).toBe('success');
        expect(standingTone('due_soon')).toBe('warning');
        expect(standingTone('overdue')).toBe('destructive');
    });
});

describe('attendeesText', () => {
    it('words one person and many', () => {
        expect(attendeesText(1)).toBe('1 persona');
        expect(attendeesText(12)).toBe('12 personas');
    });
});
