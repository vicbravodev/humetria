import { describe, expect, it } from 'vitest';
import { PUBLIC_LINKS } from '@/lib/public-nav';

describe('PUBLIC_LINKS', () => {
    it('does not advertise prices: the landing no longer shows them', () => {
        expect(PUBLIC_LINKS.map((link) => link.id)).not.toContain('precios');
    });
});
