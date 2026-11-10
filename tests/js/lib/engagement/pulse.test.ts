import { describe, expect, it } from 'vite-plus/test';

import { modeHint } from '@/lib/engagement/pulse';

describe('modeHint', () => {
    it('says what each mode looks at', () => {
        expect(modeHint('follow_up')).toContain('prioridades');
        expect(modeHint('tour')).toContain('cada tema');
        expect(modeHint('manager')).toContain('jefe');
    });

    it('says nothing of a mode it does not know', () => {
        expect(modeHint('always_open')).toBe('');
    });
});
