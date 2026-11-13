import { describe, expect, it, vi } from 'vite-plus/test';

import { paletteCommands } from '@/lib/palette-commands';

import { can } from '../support/tenant-can';

describe('paletteCommands in a read-only company', () => {
    it('offers no creating or loading when the company only reads, though the role could write', () => {
        const actionsWith = (writing: ReturnType<typeof can>) =>
            paletteCommands({
                isStaff: false,
                tenant: { slug: 'acme', role: 'owner', can: can(true, true) },
                writing,
                sidebarExpanded: true,
                impersonating: false,
                actions: {
                    visit: vi.fn(),
                    setTheme: vi.fn(),
                    toggleSidebar: vi.fn(),
                },
            }).filter(({ group }) => group === 'Acciones');

        expect(actionsWith(can(true, true)).length).toBe(2);
        expect(actionsWith(can(false, false))).toEqual([]);
    });
});
