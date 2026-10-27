import { describe, expect, it } from 'vite-plus/test';

import { whatsappUrl } from '@/lib/whatsapp-url';

describe('whatsappUrl', () => {
    it('opens a chat with the number, which wa.me takes without the plus sign', () => {
        expect(whatsappUrl('+525512345678')).toBe('https://wa.me/525512345678');
    });
});
