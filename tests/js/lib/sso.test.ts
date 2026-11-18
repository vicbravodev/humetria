import { describe, expect, it } from 'vitest';
import { splitDomains, ssoPayload } from '@/lib/sso';

describe('splitDomains', () => {
    it('takes one domain per line, comma or space and drops the blanks', () => {
        expect(
            splitDomains('acme.mx\n acme.com.mx,  \n\nfoo.mx; bar.mx'),
        ).toEqual(['acme.mx', 'acme.com.mx', 'foo.mx', 'bar.mx']);
        expect(splitDomains('  \n ')).toEqual([]);
    });
});

describe('ssoPayload', () => {
    it('sends the typed domains as a list and keeps the rest of the form', () => {
        expect(
            ssoPayload({
                issuer: 'https://idp.acme.mx',
                domains_text: 'a.mx\nb.mx',
            }),
        ).toEqual({ issuer: 'https://idp.acme.mx', domains: ['a.mx', 'b.mx'] });
        expect(ssoPayload({ issuer: 'x' })).toEqual({
            issuer: 'x',
            domains: [],
        });
    });
});
