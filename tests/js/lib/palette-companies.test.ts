import { describe, expect, it, vi } from 'vite-plus/test';

import { companyCommands } from '@/lib/palette-commands';

describe('companyCommands', () => {
    it('offers each company under «Empresas» and opens its page in the console', () => {
        const visit = vi.fn();
        const commands = companyCommands(
            [{ value: 'c1', label: 'Textiles del Norte' }],
            visit,
        );

        expect(commands.map(({ group, label }) => [group, label])).toEqual([
            ['Empresas', 'Textiles del Norte'],
        ]);

        commands[0]?.run();

        expect(visit).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/admin/companies/c1' }),
        );
    });

    it('offers nothing when no company matches', () => {
        expect(companyCommands([], vi.fn())).toEqual([]);
    });
});
