import { describe, expect, it, vi } from 'vite-plus/test';

import { peopleCommands } from '@/lib/palette-commands';

describe('peopleCommands', () => {
    it('offers each person under «Personas», with their name and job title, and opens Empleados filtered by the name', () => {
        const visit = vi.fn();
        const commands = peopleCommands(
            [
                { value: 'p1', label: 'Juan Pérez · Operador' },
                { value: 'p2', label: 'Ana López' },
            ],
            'acme',
            visit,
        );

        expect(commands.map(({ group, label }) => [group, label])).toEqual([
            ['Personas', 'Juan Pérez · Operador'],
            ['Personas', 'Ana López'],
        ]);

        commands[0]?.run();

        expect(visit).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/admin/acme/employees?q=Juan+P%C3%A9rez',
            }),
        );
    });

    it('offers nothing when nobody was found', () => {
        expect(peopleCommands([], 'acme', vi.fn())).toEqual([]);
    });
});
