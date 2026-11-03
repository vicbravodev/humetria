import { describe, expect, it } from 'vitest';
import { expiryText, linkVariant } from '@/lib/page-link';
import type { PageLinkData } from '@/types';

const link = (expiresOn: string | null): PageLinkData => ({
    state: { value: 'active', label: 'Activo' },
    url: 'https://x.test/workers/abc',
    needsRotation: false,
    version: 1,
    createdOn: '3 nov 2026',
    rotatedOn: null,
    expiresOn,
    expiresDay: null,
});

describe('linkVariant', () => {
    it('reads each state of the link as a badge', () => {
        expect(linkVariant('active')).toBe('success');
        expect(linkVariant('disabled')).toBe('secondary');
        expect(linkVariant('expired')).toBe('destructive');
    });
});

describe('expiryText', () => {
    it('says the page is permanent without a day', () => {
        expect(expiryText(link(null))).toBe(
            'No vence: es una página permanente.',
        );
    });

    it('says until when it opens', () => {
        expect(expiryText(link('15 ene 2027'))).toBe(
            'Abre hasta el 15 ene 2027.',
        );
    });
});
