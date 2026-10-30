import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CommandPaletteFooter } from '@/components/command-palette-footer';
import { CommandPaletteResults } from '@/components/command-palette-results';
import { CommandPaletteSearch } from '@/components/command-palette-search';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useSidebar } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import {
    setCommandPaletteOpen,
    useCommandPaletteOpen,
    useCommandPaletteShortcut,
} from '@/hooks/use-command-palette';
import { matchCommands, moveActive } from '@/lib/commands';
import { paletteCommands } from '@/lib/palette-commands';

const RESULTS = 'command-results';

/**
 * ⌘K (pantalla Paleta): start an action, go to a page, switch the theme or fold the sidebar by typing. It opens at
 * once, without animation, because it is reached for many times a day.
 */
export function CommandPalette() {
    useCommandPaletteShortcut();
    const open = useCommandPaletteOpen();
    const { auth, tenant, impersonator } = usePage().props;
    const { state, toggleSidebar } = useSidebar();
    const { updateAppearance } = useAppearance();
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const results = matchCommands(
        paletteCommands({
            isSuperadmin: auth.isSuperadmin,
            tenant,
            sidebarExpanded: state === 'expanded',
            impersonating: impersonator !== null,
            actions: {
                visit: (href) => router.visit(href),
                setTheme: updateAppearance,
                toggleSidebar,
            },
        }),
        query,
    );

    const close = () => {
        setCommandPaletteOpen(false);
        setQuery('');
        setActive(0);
    };
    const run = (index: number) => {
        const command = results[index];

        if (command !== undefined) {
            close();
            command.run();
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) =>
                isOpen ? setCommandPaletteOpen(true) : close()
            }
        >
            <DialogContent
                aria-describedby={undefined}
                className="top-[12vh] translate-y-0 gap-0 overflow-hidden rounded-[14px] p-0 data-[state=closed]:animate-none data-[state=open]:animate-none sm:max-w-[640px] [&>button:last-child]:hidden"
            >
                <DialogTitle className="sr-only">Buscar o ir a</DialogTitle>
                <CommandPaletteSearch
                    controls={RESULTS}
                    activeId={
                        results.length > 0 ? `${RESULTS}-${active}` : undefined
                    }
                    field={{
                        value: query,
                        onChange: (value) => {
                            setQuery(value);
                            setActive(0);
                        },
                        onKeyDown: (event) => {
                            const next = moveActive(
                                event.key,
                                active,
                                results.length,
                            );

                            if (next !== undefined || event.key === 'Enter') {
                                event.preventDefault();
                            }

                            if (next !== undefined) {
                                setActive(next);
                            } else if (event.key === 'Enter') {
                                run(active);
                            }
                        },
                    }}
                />
                <CommandPaletteResults
                    id={RESULTS}
                    results={results}
                    active={active}
                    onPoint={setActive}
                    onRun={run}
                />
                <CommandPaletteFooter />
            </DialogContent>
        </Dialog>
    );
}
