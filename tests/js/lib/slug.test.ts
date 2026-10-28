import { describe, expect, it } from 'vite-plus/test';

import { slugify } from '@/lib/slug';

describe('slugify', () => {
    it.each([
        ['Grupo Acme, S.A.', 'grupo-acme-sa'],
        ['Ñandú Café', 'nandu-cafe'],
        ['  --Hola   Mundo--  ', 'hola-mundo'],
        ['Planta_Norte 2', 'planta-norte-2'],
        ['', ''],
    ])('writes %j as %j', (text, slug) => {
        expect(slugify(text)).toBe(slug);
    });

    it('cuts at 50 characters without leaving a hyphen at the end', () => {
        const text = `${'a'.repeat(49)} b${'c'.repeat(30)}`;

        expect(slugify('a'.repeat(80))).toBe('a'.repeat(50));
        expect(slugify(text)).toBe('a'.repeat(49));
    });
});
