import type { PaletteCommand } from '@/lib/palette-commands';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    results: PaletteCommand[];
    active: number;
    onPoint: (index: number) => void;
    onRun: (index: number) => void;
};

/** The listbox under the palette's search field; the active option follows the arrows and the pointer. */
export function CommandPaletteResults({
    id,
    results,
    active,
    onPoint,
    onRun,
}: Props) {
    if (results.length === 0) {
        return (
            <p className="px-4 py-10 text-center text-muted-foreground">
                Sin resultados
            </p>
        );
    }

    return (
        <ul
            id={id}
            role="listbox"
            aria-label="Resultados"
            className="max-h-80 overflow-y-auto p-2"
        >
            {results.map(({ id: key, label, group, icon: Icon }, index) => (
                <li
                    key={key}
                    id={`${id}-${index}`}
                    role="option"
                    aria-selected={index === active}
                    onPointerMove={() => onPoint(index)}
                    onClick={() => onRun(index)}
                    className={cn(
                        'flex h-11 cursor-default items-center gap-3 rounded-md px-3 text-base',
                        index === active && 'bg-accent text-accent-foreground',
                    )}
                >
                    <Icon
                        aria-hidden
                        className="size-5 shrink-0 text-muted-foreground"
                    />
                    <span data-slot="command-label" className="flex-1 truncate">
                        {label}
                    </span>
                    <span
                        aria-hidden
                        className="text-[13px] leading-[18px] text-muted-foreground"
                    >
                        {group}
                    </span>
                </li>
            ))}
        </ul>
    );
}
