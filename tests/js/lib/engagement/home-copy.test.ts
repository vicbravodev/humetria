import { describe, expect, it } from 'vite-plus/test';

import { homeCopy } from '@/lib/engagement/panel';

describe('homeCopy', () => {
    it('names the climate survey and offers the first one when there is no history', () => {
        const copy = homeCopy('climate', 0);

        expect(copy.title).toBe('Clima');
        expect(copy.createLabel).toBe('Crear la primera encuesta de clima');
    });

    it('offers another climate survey once there is history', () => {
        expect(homeCopy('climate', 3).createLabel).toBe(
            'Crear una encuesta de clima',
        );
    });

    it('names the pulse and says it is short', () => {
        const copy = homeCopy('pulse', 0);

        expect(copy.title).toBe('Pulso');
        expect(copy.createLabel).toBe('Crear el primer pulso');
        expect(copy.description).toContain('corta');
        expect(homeCopy('pulse', 2).createLabel).toBe('Crear un pulso');
    });
});
