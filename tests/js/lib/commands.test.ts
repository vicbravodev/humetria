import { describe, expect, it } from 'vite-plus/test';

import { matchCommands, moveActive } from '@/lib/commands';

const commands = [
    { id: 'panel', label: 'Panel', keywords: ['inicio'] },
    { id: 'companies', label: 'Empresas', keywords: ['clientes'] },
    { id: 'dark', label: 'Usar tema oscuro', keywords: ['noche'] },
];

describe('matchCommands', () => {
    it('lists every command when nothing is typed', () => {
        expect(matchCommands(commands, '   ')).toEqual(commands);
    });

    it('matches part of the label, ignoring case and accents', () => {
        expect(matchCommands(commands, 'EMPR').map(({ id }) => id)).toEqual([
            'companies',
        ]);
        expect(matchCommands(commands, 'oscúro').map(({ id }) => id)).toEqual([
            'dark',
        ]);
    });

    it('matches a keyword too', () => {
        expect(matchCommands(commands, 'cliente').map(({ id }) => id)).toEqual([
            'companies',
        ]);
    });

    it('finds nothing for text no command has', () => {
        expect(matchCommands(commands, 'reportes')).toEqual([]);
    });
});

describe('moveActive', () => {
    it.each([
        ['ArrowDown', 1, 2],
        ['ArrowUp', 1, 0],
        ['Home', 2, 0],
        ['End', 0, 3],
        ['ArrowDown', 3, 3],
        ['ArrowUp', 0, 0],
    ])('moves %s from %i to %i in a list of 4', (key, from, to) => {
        expect(moveActive(key, from, 4)).toBe(to);
    });

    it('stays at the top of an empty list and ignores other keys', () => {
        expect(moveActive('ArrowDown', 0, 0)).toBe(0);
        expect(moveActive('a', 2, 4)).toBeUndefined();
    });
});
