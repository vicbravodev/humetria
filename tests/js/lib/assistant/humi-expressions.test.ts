import { describe, expect, it } from 'vite-plus/test';
import {
    HUMI_FACES,
    HUMI_TICKS,
    litTicks,
} from '@/lib/assistant/humi-expressions';

describe('humi faces', () => {
    it('has the five ticks of the arch of the logo, from left to right', () => {
        expect(HUMI_TICKS).toHaveLength(5);
        expect(HUMI_TICKS[2]).toEqual([160, 58, 160, 42]);
    });

    it('gives each expression its own mouth, and the happy one no pupils', () => {
        expect(HUMI_FACES.hola.mouth).not.toBe(HUMI_FACES.escucha.mouth);
        expect(HUMI_FACES.calcula.mouth).not.toBe(HUMI_FACES.listo.mouth);
        expect(HUMI_FACES.listo.pupils).toBeNull();
        expect(HUMI_FACES.hola.pupils).not.toBeNull();
    });
});

describe('litTicks', () => {
    it('lights the ticks the expression carries by default', () => {
        expect(litTicks('hola')).toBe(3);
        expect(litTicks('escucha')).toBe(1);
        expect(litTicks('listo')).toBe(5);
    });

    it('lights the ticks asked for, rounded and kept between none and five', () => {
        expect(litTicks('hola', 2.4)).toBe(2);
        expect(litTicks('hola', 2.5)).toBe(3);
        expect(litTicks('hola', 0)).toBe(0);
        expect(litTicks('hola', -3)).toBe(0);
        expect(litTicks('hola', 9)).toBe(5);
    });
});
