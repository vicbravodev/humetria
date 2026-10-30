import { Search } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { DialogClose } from '@/components/ui/dialog';

/**
 * The palette's search field (pantalla Paleta): a combobox over the results, and Esc at its end, which also closes
 * the palette on a touch.
 */
export function CommandPaletteSearch({
    controls,
    activeId,
    field,
}: {
    controls: string;
    activeId: string | undefined;
    field: {
        value: string;
        onChange: (value: string) => void;
        onKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
    };
}) {
    return (
        <div className="flex h-15 items-center gap-3 border-b pr-2 pl-4">
            <Search
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
            <input
                id="command-search"
                role="combobox"
                aria-label="Buscar"
                aria-expanded={activeId !== undefined}
                aria-controls={controls}
                aria-autocomplete="list"
                aria-activedescendant={activeId}
                autoComplete="off"
                placeholder="Busca una página o una acción"
                value={field.value}
                onChange={(event) => field.onChange(event.target.value)}
                onKeyDown={field.onKeyDown}
                className="h-full min-w-0 flex-1 bg-transparent text-lg outline-hidden placeholder:text-muted-foreground"
            />
            <DialogClose className="inline-flex size-11 shrink-0 items-center justify-center rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <kbd
                    aria-hidden
                    className="rounded-[5px] border bg-muted px-1.5 text-xs leading-[18px] text-muted-foreground"
                >
                    Esc
                </kbd>
                <span className="sr-only">Cerrar</span>
            </DialogClose>
        </div>
    );
}
