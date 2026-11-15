import { router, usePage } from '@inertiajs/react';
import { type KeyboardEvent, useState } from 'react';
import { CommandPaletteFooter } from '@/components/command-palette-footer';
import { CommandPaletteResults } from '@/components/command-palette-results';
import { CommandPaletteSearch } from '@/components/command-palette-search';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useSidebar } from '@/components/ui/sidebar';
import { useNavTenant } from '@/hooks/use-nav-tenant';
import { usePalettePeople } from '@/hooks/use-palette-people';
import { useAppearance } from '@/hooks/use-appearance';
import {
    setCommandPaletteOpen,
    useCommandPaletteOpen,
    useCommandPaletteShortcut,
} from '@/hooks/use-command-palette';
import { matchCommands, moveActive } from '@/lib/commands';
import { paletteCommands, peopleCommands } from '@/lib/palette-commands';

const RESULTS = 'command-results';

/** The arrows move through the results and Enter runs the one in view; any other key is the field's. */
function navigate(
    event: KeyboardEvent<HTMLInputElement>,
    at: { active: number; count: number },
    to: { move: (index: number) => void; run: () => void },
) {
    const next = moveActive(event.key, at.active, at.count);

    if (next !== undefined || event.key === 'Enter') {
        event.preventDefault();
    }

    if (next !== undefined) {
        to.move(next);
    } else if (event.key === 'Enter') {
        to.run();
    }
}

/**
 * ⌘K (pantalla Paleta): start an action, go to a page, switch the theme or fold the sidebar by typing. It opens at
 * once, without animation, because it is reached for many times a day.
 */
export function CommandPalette() {
    useCommandPaletteShortcut();
    const open = useCommandPaletteOpen();
    const { auth, impersonator, tenant: current } = usePage().props;
    const tenant = useNavTenant();
    const { state, toggleSidebar } = useSidebar();
    const { updateAppearance } = useAppearance();
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const people = usePalettePeople(
        query,
        tenant?.slug ?? null,
        tenant?.can.manage_organization === true,
    );
    const pages = matchCommands(
        paletteCommands({
            isStaff: auth.isStaff,
            isSuperadmin: auth.isSuperadmin,
            tenant,
            writing: current?.can,
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
    // The server already matched the people, so they are not filtered again by their label.
    const results = [
        ...pages,
        ...(tenant === null
            ? []
            : peopleCommands(people, tenant.slug, (href) =>
                  router.visit(href),
              )),
    ];

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
                        onKeyDown: (event) =>
                            navigate(
                                event,
                                { active, count: results.length },
                                {
                                    move: setActive,
                                    run: () => run(active),
                                },
                            ),
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
