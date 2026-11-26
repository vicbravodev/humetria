import { describe, expect, it } from 'vite-plus/test';
import { climateReached, climateUnreached } from '@/lib/climate-contact';

describe('climateReached', () => {
    it('says everyone has an email when nobody goes by WhatsApp', () => {
        expect(climateReached({ people: 6, whatsapp: 0 })).toBe(
            'Todas tienen correo: a cada una le llega su enlace.',
        );
    });

    it('says how many go by email and how many by WhatsApp', () => {
        expect(climateReached({ people: 6, whatsapp: 2 })).toBe(
            'Todas tienen correo o celular: 4 reciben su enlace por correo y 2 por WhatsApp.',
        );
        expect(climateReached({ people: 2, whatsapp: 1 })).toBe(
            'Todas tienen correo o celular: 1 recibe su enlace por correo y 1 por WhatsApp.',
        );
    });

    it('says WhatsApp alone when everyone goes by it', () => {
        expect(climateReached({ people: 3, whatsapp: 3 })).toBe(
            'Todas tienen celular: a cada una le llega su enlace por WhatsApp.',
        );
    });
});

describe('climateUnreached', () => {
    it('says who has neither an email nor a phone', () => {
        expect(climateUnreached(1)).toBe(
            '1 persona no tiene correo ni celular.',
        );
        expect(climateUnreached(2)).toBe(
            '2 personas no tienen correo ni celular.',
        );
    });
});
